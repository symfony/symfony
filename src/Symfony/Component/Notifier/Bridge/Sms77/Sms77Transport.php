<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Sms77;

use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author André Matthies <matthiez@gmail.com>
 */
final class Sms77Transport extends AbstractTransport
{
    protected const HOST = 'gateway.sms77.io';

    private $apiKey;
    private $from;

    public function __construct(#[\SensitiveParameter] string $apiKey, string $from = null, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->apiKey = $apiKey;
        $this->from = $from;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        if (null === $this->from) {
            return sprintf('sms77://%s', $this->getEndpoint());
        }

        return sprintf('sms77://%s?from=%s', $this->getEndpoint(), $this->from);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage;
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof SmsMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, SmsMessage::class, $message);
        }

        $from = $message->getFrom() ?: $this->from;

        $endpoint = sprintf('https://%s/api/sms', $this->getEndpoint());
        $response = $this->client->request('POST', $endpoint, [
            'headers' => [
                'Content-Type' => 'application/json',
                'SentWith' => 'Symfony Notifier',
                'X-Api-Key' => $this->apiKey,
            ],
            'json' => [
                'from' => $from,
                'json' => 1,
                'text' => $message->getSubject(),
                'to' => $message->getPhone(),
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote Sms77 server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            $error = $response->toArray(false);

            throw new TransportException(sprintf('Unable to send the SMS: "%s" (%s).', $error['description'], $error['code']), $response);
        }

        $success = $response->toArray(false);

        if (false === \in_array($success['success'], [100, 101])) {
            throw new TransportException(sprintf('Unable to send the SMS: "%s".', $success['success']), $response);
        }

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId((int) $success['messages'][0]['id']);

        return $sentMessage;
    }
}
