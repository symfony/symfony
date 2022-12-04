<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\LineNotify;

use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Akira Kurozumi <info@a-zumi.net>
 */
final class LineNotifyTransport extends AbstractTransport
{
    protected const HOST = 'notify-api.line.me';

    private string $token;

    public function __construct(#[\SensitiveParameter] string $token, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->token = $token;
        parent::__construct($client, $dispatcher);
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof ChatMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, ChatMessage::class, $message);
        }

        $content = $message->getSubject();

        $endpoint = sprintf('https://%s/api/notify', $this->getEndpoint());
        $response = $this->client->request('POST', $endpoint, [
            'auth_bearer' => $this->token,
            'query' => [
                'message' => $content,
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportException $e) {
            throw new TransportException('Could not reach the remote Line server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            $result = $response->toArray(false);

            $originalContent = $message->getSubject();
            $errorCode = $result['status'];
            $errorMessage = trim($result['message']);
            throw new TransportException(sprintf('Unable to post the Line message: "%s" (%d: "%s").', $originalContent, $errorCode, $errorMessage), $response);
        }

        return new SentMessage($message, (string) $this);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof ChatMessage;
    }

    public function __toString(): string
    {
        return sprintf('linenotify://%s', $this->getEndpoint());
    }
}
