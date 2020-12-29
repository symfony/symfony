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
use Symfony\Component\HttpClient\Exception\TransportException as HttpClientTransportException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class EsendexTransport extends AbstractTransport
{
    protected const HOST = 'api.esendex.com';

    private $email;
    private $password;
    private $accountReference;
    private $from;

    public function __construct(string $email, string $password, string $accountReference, string $from, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->email = $email;
        $this->password = $password;
        $this->accountReference = $accountReference;
        $this->from = $from;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('esendex://%s?accountreference=%s&from=%s', $this->getEndpoint(), $this->accountReference, $this->from);
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

        $messageData = [
            'to' => $message->getPhone(),
            'body' => $message->getSubject(),
        ];

        if (null !== $this->from) {
            $messageData['from'] = $this->from;
        }

        $response = $this->client->request('POST', 'https://'.$this->getEndpoint().'/v1.0/messagedispatcher', [
            'auth_basic' => sprintf('%s:%s', $this->email, $this->password),
            'json' => [
                'accountreference' => $this->accountReference,
                'messages' => [$messageData],
            ],
        ]);

        $statusCode = $response->getStatusCode();

        if (200 === $statusCode) {
            return new SentMessage($message, (string) $this);
        }

        $message = sprintf('Unable to send the SMS: error %d.', $statusCode);

        try {
            $result = $response->toArray(false);
            if (!empty($result['errors'])) {
                $error = $result['errors'][0];

                $message .= sprintf(' Details from Esendex: %s: "%s".', $error['code'], $error['description']);
            }
        } catch (HttpClientTransportException $e) {
            // Catching this exception is useful to keep compatibility, with symfony/http-client < 4.4.10
            // See https://github.com/symfony/symfony/pull/37065
        } catch (JsonException $e) {
        }

        throw new TransportException($message, $response);
    }
}
