<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Telnyx;

use Symfony\Component\Notifier\Exception\IncompleteDsnException;
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
 * @author Vasilij Duško <vasilij@prado.lt>
 */
final class TelnyxTransport extends AbstractTransport
{
    protected const HOST = 'api.telnyx.com';

    private string $apiKey;
    private string $from;
    private ?string $messagingProfileId;

    public function __construct(#[\SensitiveParameter] string $apiKey, string $from, ?string $messagingProfileId, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->apiKey = $apiKey;
        $this->from = $from;
        $this->messagingProfileId = $messagingProfileId;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        if (null !== $this->messagingProfileId) {
            return sprintf('telnyx://%s?from=%s&messaging_profile_id=%s', $this->getEndpoint(), $this->from, $this->messagingProfileId);
        }

        return sprintf('telnyx://%s?from=%s', $this->getEndpoint(), $this->from);
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

        if (!preg_match('/^[+]+[1-9][0-9]{9,14}$/', $from)) {
            if ('' === $from) {
                throw new IncompleteDsnException('This phone number is invalid.');
            } elseif ('' !== $from && null === $this->messagingProfileId) {
                throw new IncompleteDsnException('The sending messaging profile must be specified.');
            }

            if (!preg_match('/^[a-zA-Z0-9 ]+$/', $from)) {
                throw new IncompleteDsnException('The Sender ID is invalid.');
            }
        }

        $endpoint = sprintf('https://%s/v2/messages', $this->getEndpoint());
        $response = $this->client->request('POST', $endpoint, [
            'auth_bearer' => $this->apiKey,
            'json' => [
                'from' => $from,
                'to' => $message->getPhone(),
                'text' => $message->getSubject(),
                'messaging_profile_id' => $this->messagingProfileId ?? '',
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote Telnyx server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            $error = $response->toArray(false);
            if (!isset($error['errors'])) {
                throw new TransportException('Unable to send the SMS.', $response);
            }

            throw new TransportException('Unable to send the SMS: '.$error['errors'][0]['detail'] ?? 'Unknown reason', $response);
        }

        $success = $response->toArray(false);

        $sentMessage = new SentMessage($message, (string) $this);
        if (isset($success['data']['id'])) {
            $sentMessage->setMessageId($success['data']['id']);
        }

        return $sentMessage;
    }
}
