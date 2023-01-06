<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Plivo;

use Symfony\Component\HttpClient\Exception\JsonException;
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
final class PlivoTransport extends AbstractTransport
{
    protected const HOST = 'api.plivo.com';

    public function __construct(
        private readonly string $authId,
        #[\SensitiveParameter] private readonly string $authToken,
        private readonly string $from,
        HttpClientInterface $client = null,
        EventDispatcherInterface $dispatcher = null
    ) {
        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('plivo://%s?from=%s', $this->getEndpoint(), $this->from);
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
        $options['src'] = $options['src'] ?? $this->from;
        $options['dst'] = $options['dst'] ?? $message->getPhone();

        if (!preg_match('/^[a-zA-Z0-9\s]{2,11}$/', $options['src']) && !preg_match('/^\+?[1-9]\d{1,14}$/', $options['src'])) {
            throw new InvalidArgumentException(sprintf('The "From" number "%s" is not a valid phone number, shortcode, or alphanumeric sender ID. Phone number must contain only numbers and optional + character.', $this->from));
        }

        $endpoint = sprintf('https://%s/v1/Account/%s/Message/', $this->getEndpoint(), $this->authId);
        $response = $this->client->request('POST', $endpoint, ['auth_basic' => $this->authId.':'.$this->authToken, 'json' => array_filter($options)]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote Plivo server.', $response, 0, $e);
        }

        if (202 !== $statusCode) {
            try {
                $error = $response->toArray(false);
            } catch (JsonException) {
                $error['error'] = $response->getContent(false);
            }
            throw new TransportException(sprintf('Unable to send the SMS - status code: "%s": "%s".', $statusCode, $error['error'] ?? 'unknown error'), $response);
        }

        $success = $response->toArray(false);
        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($success['message_uuid'][0]);

        return $sentMessage;
    }
}
