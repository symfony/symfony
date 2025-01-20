<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\LineBot;

use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Yi-Jyun Pan <me@pan93.com>
 */
final class LineBotTransport extends AbstractTransport
{
    protected const HOST = 'api.line.me';

    public function __construct(
        #[\SensitiveParameter] private readonly string $accessToken,
        private readonly string $receiver,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
    ) {
        parent::__construct($client, $dispatcher);
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof ChatMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, ChatMessage::class, $message);
        }

        $response = $this->client->request(
            'POST',
            \sprintf('https://%s/v2/bot/message/push', $this->getEndpoint()),
            [
                'auth_bearer' => $this->accessToken,
                'json' => [
                    'to' => $this->receiver,
                    'messages' => [
                        [
                            'type' => 'text',
                            'text' => $message->getSubject(),
                        ],
                    ],
                ],
            ],
        );

        try {
            $statusCode = $response->getStatusCode();
        } catch (\Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote LINE server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            $originalContent = $message->getSubject();

            $result = $response->toArray(false) ?: ['message' => ''];
            if (!isset($result['message']) || !\is_string($result['message'])) {
                $result['message'] = '';
            }

            throw new TransportException(\sprintf('Unable to post the LINE message: "%s" (%d: "%s").', $originalContent, $statusCode, trim($result['message'])), $response);
        }

        return new SentMessage($message, (string) $this);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof ChatMessage;
    }

    public function __toString(): string
    {
        return \sprintf('linebot://%s?receiver=%s', $this->getEndpoint(), $this->receiver);
    }
}
