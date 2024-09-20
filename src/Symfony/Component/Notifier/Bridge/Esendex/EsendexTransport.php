<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Esendex;

use Symfony\Component\HttpClient\Exception\JsonException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class EsendexTransport extends AbstractTransport
{
    protected const HOST = 'api.esendex.com';

    public function __construct(
        private string $email,
        #[\SensitiveParameter] private string $password,
        private string $accountReference,
        private string $from,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
    ) {
        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return \sprintf('esendex://%s?accountreference=%s&from=%s', $this->getEndpoint(), $this->accountReference, $this->from);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage && (null === $message->getOptions() || $message->getOptions() instanceof EsendexOptions);
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof SmsMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, SmsMessage::class, $message);
        }

        $options = $message->getOptions()?->toArray() ?? [];
        $options['from'] = $message->getFrom() ?: $this->from;
        $options['messages'] = [
            [
                'to' => $message->getPhone(),
                'body' => $message->getSubject(),
            ],
        ];
        $options['accountreference'] ??= $this->accountReference;

        $response = $this->client->request('POST', 'https://'.$this->getEndpoint().'/v1.0/messagedispatcher', [
            'auth_basic' => [$this->email, $this->password],
            'headers' => [
                'Accept' => 'application/json',
            ],
            'json' => array_filter($options),
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote Esendex server.', $response, 0, $e);
        }

        if (200 === $statusCode) {
            $result = $response->toArray();
            $sentMessage = new SentMessage($message, (string) $this);

            $messageId = $result['batch']['messageheaders'][0]['id'] ?? null;
            if ($messageId) {
                $sentMessage->setMessageId($messageId);
            }

            return $sentMessage;
        }

        $message = \sprintf('Unable to send the SMS: error %d.', $statusCode);

        try {
            $result = $response->toArray(false);
            if (!empty($result['errors'])) {
                $error = $result['errors'][0];

                $message .= \sprintf(' Details from Esendex: %s: "%s".', $error['code'], $error['description']);
            }
        } catch (JsonException) {
        }

        throw new TransportException($message, $response);
    }
}
