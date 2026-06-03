<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Novu;

use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\TransportExceptionInterface;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\PushMessage;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Wouter van der Loop <woutervdl@toppy.nl>
 */
class NovuTransport extends AbstractTransport
{
    protected const HOST = 'web.novu.co';

    public function __construct(
        #[\SensitiveParameter] protected string $apiKey,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
    ) {
        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return \sprintf('novu://%s', $this->getEndpoint());
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof PushMessage && (null === $message->getOptions() || $message->getOptions() instanceof NovuOptions);
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof PushMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, PushMessage::class, $message);
        }

        $options = $message->getOptions()?->toArray() ?? [];

        $body = [
            'name' => $message->getSubject(),
            'to' => [
                'subscriberId' => $message->getRecipientId(),
                'firstName' => $options['firstName'],
                'lastName' => $options['lastName'],
                'email' => $options['email'],
                'phone' => $options['phone'],
                'avatar' => $options['avatar'],
                'locale' => $options['locale'],
            ],
            'payload' => json_decode($message->getContent()),
            'overrides' => $options['overrides'] ?? [],
        ];

        $endpoint = \sprintf('https://%s/v1/events/trigger', $this->getEndpoint());
        $response = $this->client->request('POST', $endpoint, [
            'body' => $body,
            'headers' => [
                'Authorization' => \sprintf('ApiKey %s', $this->apiKey),
                'Content-Type' => 'application/json',
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote Novu server.', $response, 0, $e);
        }

        if (201 !== $statusCode) {
            $originalContent = $message->getSubject();
            $result = $response->toArray(false);
            $error = $result['message'];
            throw new TransportException(\sprintf('Unable to post the Novu message: "%s" (%d: "%s").', $originalContent, $statusCode, $error), $response);
        }

        return new SentMessage($message, (string) $this);
    }
}
