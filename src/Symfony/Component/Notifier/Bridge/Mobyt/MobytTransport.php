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
 * @author Basien Durand <bdurand-dev@outlook.com>
 */
final class MobytTransport extends AbstractTransport
{
    protected const HOST = 'app.mobyt.fr';

    private string $typeQuality;

    public function __construct(
        private string $accountSid,
        #[\SensitiveParameter] private string $authToken,
        private string $from,
        ?string $typeQuality = null,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
    ) {
        $typeQuality ??= MobytOptions::MESSAGE_TYPE_QUALITY_LOW;
        MobytOptions::validateMessageType($typeQuality);

        $this->typeQuality = $typeQuality;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return \sprintf('mobyt://%s?from=%s&type_quality=%s', $this->getEndpoint(), $this->from, $this->typeQuality);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage && (null === $message->getOptions() || $message->getOptions() instanceof MobytOptions);
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof SmsMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, SmsMessage::class, $message);
        }

        $options = $message->getOptions()?->toArray() ?? [];
        $options['message_type'] ??= $this->typeQuality;
        $options['message'] = $message->getSubject();
        $options['recipient'] = [$message->getPhone()];
        $options['sender'] = $message->getFrom() ?: $this->from;

        $response = $this->client->request('POST', 'https://'.$this->getEndpoint().'/API/v1.0/REST/sms', [
            'headers' => [
                'user_key' => $this->accountSid,
                'Access_token' => $this->authToken,
            ],
            'json' => array_filter($options),
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote Mobyt server.', $response, 0, $e);
        }

        if (401 === $statusCode || 404 === $statusCode) {
            throw new TransportException(\sprintf('Unable to send the SMS: "%s". Check your credentials.', $message->getSubject()), $response);
        }

        if (201 !== $statusCode) {
            $error = $response->toArray(false);

            throw new TransportException(\sprintf('Unable to send the SMS: "%s".', $error['result']), $response);
        }

        $success = $response->toArray(false);

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($success['order_id']);

        return $sentMessage;
    }
}
