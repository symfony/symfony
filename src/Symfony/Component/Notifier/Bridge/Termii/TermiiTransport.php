<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Termii;

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
final class TermiiTransport extends AbstractTransport
{
    protected const HOST = 'api.ng.termii.com';

    public function __construct(
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly string $from,
        private readonly string $channel,
        HttpClientInterface $client = null,
        EventDispatcherInterface $dispatcher = null
    ) {
        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('termii://%s?from=%s&channel=%s', $this->getEndpoint(), $this->from, $this->channel);
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
        $options['api_key'] = $this->apiKey;
        $options['sms'] = $message->getSubject();
        $options['from'] = $options['from'] ?? $this->from;
        $options['to'] = $message->getPhone();
        $options['channel'] = $options['channel'] ?? $this->channel;
        $options['type'] = $options['type'] ?? 'plain';

        if (isset($options['media_url'])) {
            $options['media']['url'] = $options['media_url'] ?? null;
            $options['media']['caption'] = $options['media_caption'] ?? null;
            unset($options['media_url'], $options['media_caption']);
        }

        if (!preg_match('/^[a-zA-Z0-9\s]{3,11}$/', $options['from']) && !preg_match('/^\+?[1-9]\d{1,14}$/', $options['from'])) {
            throw new InvalidArgumentException(sprintf('The "From" number "%s" is not a valid phone number, shortcode, or alphanumeric sender ID.', $this->from));
        }

        $endpoint = sprintf('https://%s/api/sms/send', $this->getEndpoint());
        $response = $this->client->request('POST', $endpoint, ['json' => array_filter($options)]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote Termii server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            try {
                $error = $response->toArray(false);
            } catch (JsonException) {
                $error['message'] = $response->getContent(false);
            }
            throw new TransportException(sprintf('Unable to send the SMS - status code: "%s": "%s".', $statusCode, $error['message'] ?? 'unknown error'), $response);
        }

        $success = $response->toArray(false);
        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($success['message_id']);

        return $sentMessage;
    }
}
