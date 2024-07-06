<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Sipgate;

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
 * @author Lukas Kaltenbach <lk@wikanet.de>
 */
final class SipgateTransport extends AbstractTransport
{
    protected const HOST = 'api.sipgate.com';

    public function __construct(
        private string $tokenId,
        #[\SensitiveParameter] private string $token,
        private ?string $senderId = null,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
    ) {
        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('sipgate://%s?senderId=%s', $this->getEndpoint(), $this->senderId);
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

        $endpoint = sprintf('https://%s/v2/sessions/sms', $this->getEndpoint());

        $options = [];
        $options['smsId'] = $this->senderId;
        $options['message'] = $message->getSubject();
        $options['recipient'] = $message->getPhone();

        $response = $this->client->request('POST', $endpoint, [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ],
            'auth_basic' => [$this->tokenId, $this->token],
            'body' => json_encode($options),
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote Sipgate server.', $response, 0, $e);
        }

        if (204 === $statusCode) {
            $sentMessage = new SentMessage($message, (string) $this);

            return $sentMessage;
        } elseif (401 === $statusCode) {
            throw new TransportException(sprintf('Unable to send SMS with Sipgate: Error code %d - tokenId or token is wrong.', $statusCode), $response);    
        } elseif (402 === $statusCode) {
            throw new TransportException(sprintf('Unable to send SMS with Sipgate: Error code %d - insufficient funds.', $statusCode), $response);    
        } elseif (403 === $statusCode) {
            throw new TransportException(sprintf('Unable to send SMS with Sipgate: Error code %d - no permisssion to use sms feature or password must be reset or senderId is wrong.', $statusCode), $response);
        }
        throw new TransportException(sprintf('Unable to send SMS with Sipgate: Error code %d.', $statusCode), $response);
    }
}
