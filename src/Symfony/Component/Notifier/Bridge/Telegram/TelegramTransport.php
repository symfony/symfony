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

use Symfony\Component\Notifier\Exception\LogicException;
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

    private const MEDIA_TYPES = ['photo', 'video', 'document', 'audio', 'animation'];
    private const SPOILER_MEDIA_TYPES = ['photo', 'video', 'animation'];

    public function __construct(
        #[\SensitiveParameter] private string $token,
        private ?string $chatChannel = null,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
        bool $disableHttps = false,
    ) {
        parent::__construct($client, $dispatcher);

        $this->setSsl(!$disableHttps);
    }

    public function __toString(): string
    {
        $toString = \sprintf('telegram://%s', $this->getEndpoint());

        $formattedOptions = http_build_query([
            'channel' => $this->chatChannel,
            'ssl' => 'http' === $this->getHttpScheme() ? 'false' : null,
        ], '', '&');

        if ('' !== $formattedOptions) {
            $toString .= \sprintf('?%s', $formattedOptions);
        }

        return $toString;
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
            /*
             * Escape the reserved characters that Telegram would reject and leave the markup alone:
             * paired markers (*bold*, _italic_, `code`, ~strikethrough~, ||spoiler||, [link](url)),
             * block quotations (">" at the start of a line) and custom emojis ("![").
             * Characters that are already escaped are kept as they are.
             *
             * @see https://core.telegram.org/bots/api#markdownv2-style
             */
            $text = preg_replace_callback('/\\\\[\x01-\x7E]|\|\||^(?:\*\*)?>|!\[|([.!#+\-=|{}>])/m', static fn (array $m) => isset($m[1]) ? '\\'.$m[1] : $m[0], $text);
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
        $this->ensureExclusiveOptionsNotDuplicated($options);
        $method = $this->getPath($options);
        if ('editMessageMedia' === $method) {
            $options = $this->buildInputMedia($options, $text);
        }
        unset($options['edit_caption']);
        $options = $this->expandOptions($options, 'contact', 'location', 'venue');
        $endpoint = \sprintf('%s://%s/bot%s/%s', $this->getHttpScheme(), $this->getEndpoint(), $this->token, $method);

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
            isset($options['message_id']) => $this->getEditMethod($options),
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

    private function getEditMethod(array $options): string
    {
        return match (true) {
            isset($options['photo']), isset($options['video']), isset($options['document']),
            isset($options['audio']), isset($options['animation']) => 'editMessageMedia',
            isset($options['edit_caption']) => 'editMessageCaption',
            default => 'editMessageText',
        };
    }

    private function buildInputMedia(array $options, ?string $text): array
    {
        foreach (self::MEDIA_TYPES as $type) {
            if (!isset($options[$type])) {
                continue;
            }

            if (\is_resource($options[$type])) {
                throw new LogicException('Editing a message with a locally uploaded media is not supported yet, use a URL or a Telegram file ID instead.');
            }

            $media = [
                'type' => $type,
                'media' => $options[$type],
            ];
            if (null !== $text) {
                $media['caption'] = $text;
            }
            if (isset($options['parse_mode'])) {
                $media['parse_mode'] = $options['parse_mode'];
            }
            if (isset($options['has_spoiler']) && \in_array($type, self::SPOILER_MEDIA_TYPES, true)) {
                $media['has_spoiler'] = $options['has_spoiler'];
            }

            $options['media'] = $media;
            unset($options[$type], $options['caption'], $options['parse_mode'], $options['has_spoiler']);

            break;
        }

        return $options;
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
            isset($options['edit_caption']) => 'caption',
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

        // editing a message replaces its media, so "message_id" pairs with a single media type
        if (isset($options['message_id']) && 1 === \count(array_intersect($usedOptions, self::MEDIA_TYPES))) {
            $usedExclusiveOptions = array_diff($usedExclusiveOptions, ['message_id']);
        }

        if (\count($usedExclusiveOptions) > 1) {
            throw new MultipleExclusiveOptionsUsedException($usedExclusiveOptions, self::EXCLUSIVE_OPTIONS);
        }
    }
}
