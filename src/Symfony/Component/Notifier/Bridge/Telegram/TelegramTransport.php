<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Telegram;

use Symfony\Component\Notifier\Exception\MultipleExclusiveOptionsUsedException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * To get the chat id, send a message in Telegram with the user you want
 * and then execute curl 'https://api.telegram.org/bot%token%/getUpdates' | json_pp
 * command.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class TelegramTransport extends AbstractTransport
{
    protected const HOST = 'api.telegram.org';

    private const EXCLUSIVE_OPTIONS = [
        'message_id',
        'callback_query_id',
        'photo',
        'location',
        'audio',
        'document',
        'video',
        'animation',
        'venue',
        'contact',
        'sticker',
    ];

    public function __construct(
        #[\SensitiveParameter] private string $token,
        private ?string $chatChannel = null,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
    ) {
        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        if (null === $this->chatChannel) {
            return \sprintf('telegram://%s', $this->getEndpoint());
        }

        return \sprintf('telegram://%s?channel=%s', $this->getEndpoint(), $this->chatChannel);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof ChatMessage && (null === $message->getOptions() || $message->getOptions() instanceof TelegramOptions);
    }

    /**
     * @see https://core.telegram.org/bots/api
     */
    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof ChatMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, ChatMessage::class, $message);
        }

        $options = $message->getOptions()?->toArray() ?? [];
        $optionsContainer = 'json';
        $options['chat_id'] ??= $message->getRecipientId() ?: $this->chatChannel;
        $text = $message->getSubject();

        if (!isset($options['parse_mode']) || TelegramOptions::PARSE_MODE_MARKDOWN_V2 === $options['parse_mode']) {
            $options['parse_mode'] = TelegramOptions::PARSE_MODE_MARKDOWN_V2;
            $text = preg_replace('/([_*\[\]()~`>#+\-=|{}.!\\\\])/', '\\\\$1', $text);
        }

        if (isset($options['upload'])) {
            foreach ($options['upload'] as $option => $path) {
                $options[$option] = fopen($path, 'r');
            }
            $optionsContainer = 'body';
            unset($options['upload']);
        }

        $messageOption = $this->getTextOption($options);
        if (null !== $messageOption) {
            $options[$messageOption] = $text;
        }
        $method = $this->getPath($options);
        $this->ensureExclusiveOptionsNotDuplicated($options);
        $options = $this->expandOptions($options, 'contact', 'location', 'venue');

        $endpoint = \sprintf('https://%s/bot%s/%s', $this->getEndpoint(), $this->token, $method);

        $response = $this->client->request('POST', $endpoint, [
            $optionsContainer => array_filter($options),
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote Telegram server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            $result = $response->toArray(false);

            throw new TransportException('Unable to '.$this->getAction($options).' the Telegram message: '.$result['description'].\sprintf(' (code %d).', $result['error_code']), $response);
        }

        $success = $response->toArray(false);

        $sentMessage = new SentMessage($message, (string) $this);
        if (isset($success['result']['message_id'])) {
            $sentMessage->setMessageId($success['result']['message_id']);
        }

        return $sentMessage;
    }

    private function getPath(array $options): string
    {
        return match (true) {
            isset($options['message_id']) => 'editMessageText',
            isset($options['callback_query_id']) => 'answerCallbackQuery',
            isset($options['photo']) => 'sendPhoto',
            isset($options['location']) => 'sendLocation',
            isset($options['audio']) => 'sendAudio',
            isset($options['document']) => 'sendDocument',
            isset($options['video']) => 'sendVideo',
            isset($options['animation']) => 'sendAnimation',
            isset($options['venue']) => 'sendVenue',
            isset($options['contact']) => 'sendContact',
            isset($options['sticker']) => 'sendSticker',
            default => 'sendMessage',
        };
    }

    private function getAction(array $options): string
    {
        return match (true) {
            isset($options['message_id']) => 'edit',
            isset($options['callback_query_id']) => 'answer callback query',
            default => 'post',
        };
    }

    private function getTextOption(array $options): ?string
    {
        return match (true) {
            isset($options['photo']) => 'caption',
            isset($options['audio']) => 'caption',
            isset($options['document']) => 'caption',
            isset($options['video']) => 'caption',
            isset($options['animation']) => 'caption',
            isset($options['sticker']) => null,
            isset($options['location']) => null,
            isset($options['venue']) => null,
            isset($options['contact']) => null,
            default => 'text',
        };
    }

    private function expandOptions(array $options, string ...$optionsForExpand): array
    {
        foreach ($optionsForExpand as $optionForExpand) {
            if (isset($options[$optionForExpand])) {
                if (\is_array($options[$optionForExpand])) {
                    $options = array_merge($options, $options[$optionForExpand]);
                }
                unset($options[$optionForExpand]);
            }
        }

        return $options;
    }

    private function ensureExclusiveOptionsNotDuplicated(array $options): void
    {
        $usedOptions = array_keys($options);
        $usedExclusiveOptions = array_intersect($usedOptions, self::EXCLUSIVE_OPTIONS);
        if (\count($usedExclusiveOptions) > 1) {
            throw new MultipleExclusiveOptionsUsedException($usedExclusiveOptions, self::EXCLUSIVE_OPTIONS);
        }
    }
}
