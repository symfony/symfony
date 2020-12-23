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

    private $token;
    private $chatChannel;

    public function __construct(string $token, string $channel = null, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
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

        $endpoint = sprintf('https://%s/bot%s/sendMessage', $this->getEndpoint(), $this->token);
        $options = ($opts = $message->getOptions()) ? $opts->toArray() : [];
        if (!isset($options['chat_id'])) {
            $options['chat_id'] = $message->getRecipientId() ?: $this->chatChannel;
        }

        $options['text'] = $message->getSubject();

        if (!isset($options['parse_mode'])) {
            $options['parse_mode'] = TelegramOptions::PARSE_MODE_MARKDOWN_V2;
        }

        $response = $this->client->request('POST', $endpoint, [
            'json' => array_filter($options),
        ]);

        if (200 !== $response->getStatusCode()) {
            $result = $response->toArray(false);

            throw new TransportException('Unable to post the Telegram message: '.$result['description'].sprintf(' (code %s).', $result['error_code']), $response);
        }

        $success = $response->toArray(false);

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($success['result']['message_id']);

        return $sentMessage;
    }
}
