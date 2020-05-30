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
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * TelegramTransport.
 *
 * To get the chat id, send a message in Telegram with the user you want
 * and then curl 'https://api.telegram.org/bot%token%/getUpdates' | json_pp
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @internal
 *
 * @experimental in 5.1
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
        return sprintf('telegram://%s?channel=%s', $this->getEndpoint(), $this->chatChannel);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof ChatMessage;
    }

    /**
     * @see https://core.telegram.org/bots/api
     */
    protected function doSend(MessageInterface $message): void
    {
        if (!$message instanceof ChatMessage) {
            throw new LogicException(sprintf('The "%s" transport only supports instances of "%s" (instance of "%s" given).', __CLASS__, ChatMessage::class, get_debug_type($message)));
        }

        $endpoint = sprintf('https://%s/bot%s/sendMessage', $this->getEndpoint(), $this->token);
        $options = ($opts = $message->getOptions()) ? $opts->toArray() : [];
        if (!isset($options['chat_id'])) {
            $options['chat_id'] = $message->getRecipientId() ?: $this->chatChannel;
        }
        $options['text'] = $message->getSubject();
        $options['parse_mode'] = 'Markdown';
        $response = $this->client->request('POST', $endpoint, [
            'json' => array_filter($options),
        ]);

        if (200 !== $response->getStatusCode()) {
            $result = $response->toArray(false);

            throw new TransportException('Unable to post the Telegram message: '.$result['description'].sprintf(' (code %s).', $result['error_code']), $response);
        }
    }
}
