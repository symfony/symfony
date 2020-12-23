<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Sinch;

use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Iliya Miroslavov Iliev <i.miroslavov@gmail.com>
 */
final class SinchTransport extends AbstractTransport
{
    protected const HOST = 'sms.api.sinch.com';

    private $accountSid;
    private $authToken;
    private $from;

    public function __construct(string $accountSid, string $authToken, string $from, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->accountSid = $accountSid;
        $this->authToken = $authToken;
        $this->from = $from;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('sinch://%s?from=%s', $this->getEndpoint(), $this->from);
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

        $endpoint = sprintf('https://%s/xms/v1/%s/batches', $this->getEndpoint(), $this->accountSid);
        $response = $this->client->request('POST', $endpoint, [
            'auth_bearer' => $this->authToken,
            'json' => [
                'from' => $this->from,
                'to' => [$message->getPhone()],
                'body' => $message->getSubject(),
            ],
        ]);

        if (201 !== $response->getStatusCode()) {
            $error = $response->toArray(false);

            throw new TransportException(sprintf('Unable to send the SMS: %s (%s).', $error['text'], $error['code']), $response);
        }

        $success = $response->toArray(false);

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($success['id']);

        return $sentMessage;
    }
}
