<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Discord;

use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Mathieu Piot <math.piot@gmail.com>
 *
 * @internal
 *
 * @experimental in 5.2
 */
final class DiscordTransport extends AbstractTransport
{
    protected const HOST = 'discord.com';

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
        return sprintf('discord://%s?channel=%s', $this->getEndpoint(), $this->chatChannel);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof ChatMessage;
    }

    /**
     * @see https://discord.com/developers/docs/resources/webhook
     */
    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof ChatMessage) {
            throw new LogicException(sprintf('The "%s" transport only supports instances of "%s" (instance of "%s" given).', __CLASS__, ChatMessage::class, get_debug_type($message)));
        }

        $endpoint = sprintf('https://%s/api/webhooks/%s/%s', $this->getEndpoint(), $this->token, $this->chatChannel);
        $options['content'] = $message->getSubject();
        $response = $this->client->request('POST', $endpoint, [
            'json' => array_filter($options),
        ]);

        if (204 !== $response->getStatusCode()) {
            $result = $response->toArray(false);

            throw new TransportException(sprintf('Unable to post the Discord message: "%s" (%s).', $result['message'], $result['code']), $response);
        }
    }
}
