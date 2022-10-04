<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Firebase;

use Symfony\Component\Notifier\Exception\InvalidArgumentException;
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
 * @author Jeroen Spee <https://github.com/Jeroeny>
 */
final class FirebaseTransport extends AbstractTransport
{
    protected const HOST = 'fcm.googleapis.com/fcm/send';

    private string $token;

    public function __construct(#[\SensitiveParameter] string $token, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->token = $token;
        $this->client = $client;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('firebase://%s', $this->getEndpoint());
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof ChatMessage;
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof ChatMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, ChatMessage::class, $message);
        }

        $endpoint = sprintf('https://%s', $this->getEndpoint());
        $options = ($opts = $message->getOptions()) ? $opts->toArray() : [];
        if (!isset($options['to'])) {
            $options['to'] = $message->getRecipientId();
        }
        if (null === $options['to']) {
            throw new InvalidArgumentException(sprintf('The "%s" transport required the "to" option to be set.', __CLASS__));
        }
        $options['notification'] ??= [];
        $options['notification']['body'] = $message->getSubject();
        $options['data'] ??= [];

        $response = $this->client->request('POST', $endpoint, [
            'headers' => [
                'Authorization' => sprintf('key=%s', $this->token),
            ],
            'json' => array_filter($options),
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote Firebase server.', $response, 0, $e);
        }

        $contentType = $response->getHeaders(false)['content-type'][0] ?? '';
        $jsonContents = str_starts_with($contentType, 'application/json') ? $response->toArray(false) : null;
        $errorMessage = null;

        if ($jsonContents && isset($jsonContents['results'][0]['error'])) {
            $errorMessage = $jsonContents['results'][0]['error'];
        } elseif (200 !== $statusCode) {
            $errorMessage = $response->getContent(false);
        }

        if (null !== $errorMessage) {
            throw new TransportException('Unable to post the Firebase message: '.$errorMessage, $response);
        }

        $success = $response->toArray(false);

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($success['results'][0]['message_id'] ?? '');

        return $sentMessage;
    }
}
