<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\SmsFactor;

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
 * @author Thibault Buathier <thibault.buathier@gmail.com>
 */
final class SmsFactorTransport extends AbstractTransport
{
    protected const HOST = 'api.smsfactor.com';

    private string $tokenApi;
    private ?string $sender;
    private ?SmsFactorPushType $pushType;

    public function __construct(#[\SensitiveParameter] string $tokenApi, ?string $sender, ?SmsFactorPushType $pushType, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->tokenApi = $tokenApi;
        $this->sender = $sender;
        $this->pushType = $pushType;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        $arguments = [];
        if (null !== $this->sender) {
            $arguments[] = sprintf('sender=%s', $this->sender);
        }

        if (null !== $this->pushType) {
            $arguments[] = sprintf('push_type=%s', $this->pushType->value);
        }

        return sprintf('sms-factor://%s?%s', $this->getEndpoint(), implode('&', $arguments));
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

        $messageId = bin2hex(random_bytes(7));
        $query = [
            'to' => $message->getPhone(),
            'text' => $message->getSubject(),
            'gsmsmsid' => $messageId,
        ];

        if ('' !== $message->getFrom()) {
            $query['sender'] = $message->getFrom();
        } elseif (null !== $this->sender) {
            $query['sender'] = $this->sender;
        }

        if (null !== $this->pushType) {
            $query['pushtype'] = $this->pushType->value;
        }

        $response = $this->client->request('GET', 'https://'.$this->getEndpoint().'/send', [
            'query' => $query,
            'headers' => [
                'Authorization' => sprintf('Bearer %s', $this->tokenApi),
                'Accept' => 'application/json',
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote SMSFactor server.', $response, 0, $e);
        }

        $responseData = $response->toArray(false);

        if (1 !== $responseData['status'] ?? null || 200 !== $statusCode) {
            throw new TransportException('Unable to send the SMS: '.$responseData['details'] ?? $responseData['message'] ?? 'Unknown error', $response);
        }
        if (0 < $responseData['invalid'] ?? 0) {
            throw new TransportException('Unable to send the SMS: Invalid phone number', $response);
        }
        if (1 !== $responseData['sent'] ?? 0) {
            throw new TransportException('Unable to send the SMS: Unknown error', $response);
        }

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($messageId);

        return $sentMessage;
    }
}
