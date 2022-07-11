<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Gitter;

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
 * @author Christin Gruber <c.gruber@touchdesign.de>
 */
final class GitterTransport extends AbstractTransport
{
    protected const HOST = 'api.gitter.im';

    private string $token;
    private string $roomId;

    public function __construct(#[\SensitiveParameter] string $token, string $roomId, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->token = $token;
        $this->roomId = $roomId;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('gitter://%s?room_id=%s', $this->getEndpoint(), $this->roomId);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof ChatMessage;
    }

    /**
     * @see https://developer.gitter.im/docs/rest-api
     */
    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof ChatMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, ChatMessage::class, $message);
        }

        $endpoint = sprintf('https://%s/v1/rooms/%s/chatMessages', $this->getEndpoint(), $this->roomId);

        $response = $this->client->request('POST', $endpoint, [
            'auth_bearer' => $this->token,
            'json' => [
                'text' => $message->getSubject(),
            ],
        ]);

        try {
            $result = $response->toArray(false);
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote Gitter server.', $response, 0, $e);
        }

        if (200 !== $response->getStatusCode()) {
            throw new TransportException(sprintf('Unable to post the Gitter message: "%s".', $result['error']), $response);
        }

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($result['id']);

        return $sentMessage;
    }
}
