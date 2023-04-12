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

    private string $token;
    private ?string $chatChannel;

    public function __construct(#[\SensitiveParameter] string $token, string $channel = null, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->token = $token;
        $this->chatChannel = $channel;
        $this->client = $client;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        if (null === $this->chatChannel) {
            return sprintf('telegram://%s', $this->getEndpoint());
        }

        return sprintf('telegram://%s?channel=%s', $this->getEndpoint(), $this->chatChannel);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof ChatMessage;
    }

    /**
     * @see https://core.telegram.org/bots/api
     */
    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof ChatMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, ChatMessage::class, $message);
        }

        if ($message->getOptions() && !$message->getOptions() instanceof TelegramOptions) {
            throw new LogicException(sprintf('The "%s" transport only supports instances of "%s" for options.', __CLASS__, TelegramOptions::class));
        }

        $options = ($opts = $message->getOptions()) ? $opts->toArray() : [];
        if (!isset($options['chat_id'])) {
            $options['chat_id'] = $message->getRecipientId() ?: $this->chatChannel;
        }

        $options['text'] = $message->getSubject();

        if (!isset($options['parse_mode']) || TelegramOptions::PARSE_MODE_MARKDOWN_V2 === $options['parse_mode']) {
            $options['parse_mode'] = TelegramOptions::PARSE_MODE_MARKDOWN_V2;
            $options['text'] = preg_replace('/([_*\[\]()~`>#+\-=|{}.!])/', '\\\\$1', $message->getSubject());
        }

        if (isset($options['photo'])) {
            $options['caption'] = $options['text'];
            unset($options['text']);
        }

        $endpoint = sprintf('https://%s/bot%s/%s', $this->getEndpoint(), $this->token, $this->getPath($options));

        $response = $this->client->request('POST', $endpoint, [
            'json' => array_filter($options),
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote Telegram server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            $result = $response->toArray(false);

            throw new TransportException('Unable to '.$this->getAction($options).' the Telegram message: '.$result['description'].sprintf(' (code %d).', $result['error_code']), $response);
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
}
