<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Mobyt;

use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Basien Durand <bdurand-dev@outlook.com>
 */
final class MobytTransport extends AbstractTransport
{
    protected const HOST = 'app.mobyt.fr';

    private $accountSid;
    private $authToken;
    private $from;
    private $typeQuality;

    public function __construct(string $accountSid, string $authToken, string $from, string $typeQuality, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->accountSid = $accountSid;
        $this->authToken = $authToken;
        $this->from = $from;
        $this->typeQuality = $typeQuality;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('mobyt://%s?from=%s&type_quality=%s', $this->getEndpoint(), $this->from, $this->typeQuality);
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

        if ($message->getOptions() && !$message->getOptions() instanceof MobytOptions) {
            throw new LogicException(sprintf('The "%s" transport only supports instances of "%s" for options.', __CLASS__, MobytOptions::class));
        }

        $options = $message->getOptions() ? $message->getOptions()->toArray() : [];
        $options['message_type'] = $options['message_type'] ?? $this->typeQuality;

        $options['message'] = $options['message'] ?? $message->getSubject();
        $options['recipient'] = [$message->getPhone()];

        $options['sender'] = $options['sender'] ?? $this->from;

        $response = $this->client->request('POST', 'https://'.$this->getEndpoint().'/API/v1.0/REST/sms', [
            'headers' => [
                'Content-type: application/json',
                'user_key: '.$this->accountSid,
                'Access_token: '.$this->authToken,
            ],
            'body' => json_encode(array_filter($options)),
        ]);

        if (401 === $response->getStatusCode() || 404 === $response->getStatusCode()) {
            throw new TransportException(sprintf('Unable to send the SMS: "%s". Check your credentials.', $message->getSubject()), $response);
        }

        if (201 !== $response->getStatusCode()) {
            $error = $response->toArray(false);

            throw new TransportException(sprintf('Unable to send the SMS: "%s".', $error['result']), $response);
        }

        $success = $response->toArray(false);

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($success['order_id']);

        return $sentMessage;
    }
}
