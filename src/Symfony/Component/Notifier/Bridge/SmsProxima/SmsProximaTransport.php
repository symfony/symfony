<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\SmsProxima;

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
 * @author SMS Proxima <contact@sms-proxima.com>
 */
final class SmsProximaTransport extends AbstractTransport
{
    protected const HOST = 'sms-proxima.com';

    public function __construct(
        #[\SensitiveParameter] private string $token,
        private string $from,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
    ) {
        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return \sprintf('sms-proxima://%s?from=%s', $this->getEndpoint(), urlencode($this->from));
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

        $body = [
            'to' => $message->getPhone(),
            'sender' => $from,
            'message' => $message->getSubject(),
            'stop' => 1,
        ];

        $headers = [
            'Authorization' => 'Bearer '.$this->token,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        if ($message->getOptions() instanceof SmsProximaOptions) {
            $opts = $message->getOptions()->toArray();

            if (isset($opts['stop'])) {
                $body['stop'] = $opts['stop'];
            }
            if (isset($opts['sandbox'])) {
                $body['sandbox'] = $opts['sandbox'];
            }
            if (isset($opts['timeToSend'])) {
                $body['timeToSend'] = $opts['timeToSend'];
            }
            if (isset($opts['idempotencyKey'])) {
                $headers['Idempotency-Key'] = $opts['idempotencyKey'];
            }
        }

        $endpoint = \sprintf('%s://%s/api/sms/send', $this->getHttpScheme(), $this->getEndpoint());
        $response = $this->client->request('POST', $endpoint, [
            'headers' => $headers,
            'json' => $body,
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote SMS Proxima server.', $response, 0, $e);
        }

        $responseData = $response->toArray(false);

        if (200 !== $statusCode) {
            throw new TransportException(\sprintf('Unable to send the SMS: "%s" (%s).', $responseData['message'] ?? 'Unknown error', $responseData['code'] ?? $statusCode), $response);
        }

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($responseData['ticket'] ?? '');

        return $sentMessage;
    }
}
