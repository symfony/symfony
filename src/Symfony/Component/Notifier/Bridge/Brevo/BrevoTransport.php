<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Brevo;

use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Exception\UnsupportedOptionsException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Pierre Tanguy
 */
final class BrevoTransport extends AbstractTransport
{
    protected const HOST = 'api.brevo.com';

    public function __construct(
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly string $sender,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
    ) {
        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return \sprintf('brevo://%s?sender=%s', $this->getEndpoint(), $this->sender);
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

        $sender = $message->getFrom() ?: $this->sender;

        if (($options = $message->getOptions()) && !$options instanceof BrevoOptions) {
            throw new UnsupportedOptionsException(__CLASS__, BrevoOptions::class, $options);
        }

        $options = $options?->toArray() ?? [];

        $body = [
            'sender' => $sender,
            'recipient' => $message->getPhone(),
            'content' => $message->getSubject(),
        ];
        if (isset($options['webUrl'])) {
            $body['webUrl'] = $options['webUrl'];
        }
        if (isset($options['type'])) {
            $body['type'] = $options['type'];
        }
        if (isset($options['tag'])) {
            $body['tag'] = $options['tag'];
        }

        $response = $this->client->request('POST', 'https://'.$this->getEndpoint().'/v3/transactionalSMS/sms', [
            'json' => $body,
            'headers' => [
                'api-key' => $this->apiKey,
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote Brevo server.', $response, 0, $e);
        }

        if (201 !== $statusCode) {
            $error = $response->toArray(false);

            throw new TransportException('Unable to send the SMS: '.($error['message'] ?? $response->getContent(false)), $response);
        }

        $success = $response->toArray(false);

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($success['messageId']);

        return $sentMessage;
    }
}
