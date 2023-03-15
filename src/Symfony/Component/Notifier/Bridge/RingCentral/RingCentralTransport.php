<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\RingCentral;

use Symfony\Component\Notifier\Exception\InvalidArgumentException;
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
 * @author gnito-org <https://github.com/gnito-org>
 */
final class RingCentralTransport extends AbstractTransport
{
    protected const HOST = 'platform.ringcentral.com';

    public function __construct(
        #[\SensitiveParameter] private readonly string $apiToken,
        private readonly string $from,
        HttpClientInterface $client = null,
        EventDispatcherInterface $dispatcher = null
    ) {
        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('ringcentral://%s?from=%s', $this->getEndpoint(), $this->from);
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

        $opts = $message->getOptions();
        $options = $opts ? $opts->toArray() : [];
        $options['text'] = $message->getSubject();
        $options['from']['phoneNumber'] = $options['from'] ?? $this->from;
        $options['to'][]['phoneNumber'] = $message->getPhone();

        if (isset($options['country_id'])) {
            $options['country']['id'] = $options['country_id'] ?? null;
            $options['country']['uri'] = $options['country_uri'] ?? null;
            $options['country']['name'] = $options['country_name'] ?? null;
            $options['country']['isoCode'] = $options['country_iso_code'] ?? null;
            $options['country']['callingCode'] = $options['country_calling_code'] ?? null;
            unset($options['country_id'], $options['country_uri'], $options['country_name'], $options['country_iso_code'], $options['country_calling_code']);
        }

        if (!preg_match('/^\+[1-9]\d{1,14}$/', $options['from']['phoneNumber'])) {
            throw new InvalidArgumentException(sprintf('The "From" number "%s" is not a valid phone number. Phone number must be in E.164 format.', $this->from));
        }

        $endpoint = sprintf('https://%s/restapi/v1.0/account/~/extension/~/sms', $this->getEndpoint());
        $response = $this->client->request('POST', $endpoint, ['auth_bearer' => $this->apiToken, 'json' => array_filter($options)]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote Ring Central server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            $error = $response->toArray(false);
            throw new TransportException(sprintf('Unable to send the SMS - "%s".', $error['message'] ?? $error['error_description'] ?? $error['description'] ?? 'unknown failure'), $response);
        }

        $success = $response->toArray(false);
        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($success['id']);

        return $sentMessage;
    }
}
