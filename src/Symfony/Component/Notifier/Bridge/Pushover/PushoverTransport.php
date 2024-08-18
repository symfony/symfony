<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Pushover;

use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\PushMessage;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author mocodo <https://github.com/mocodo>
 */
final class PushoverTransport extends AbstractTransport
{
    protected const HOST = 'api.pushover.net';

    public function __construct(
        #[\SensitiveParameter] private readonly string $userKey,
        #[\SensitiveParameter] private readonly string $appToken,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
    ) {
        parent::__construct($client, $dispatcher);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof PushMessage && (null === $message->getOptions() || $message->getOptions() instanceof PushoverOptions);
    }

    public function __toString(): string
    {
        return \sprintf('pushover://%s', $this->getEndpoint());
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof PushMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, PushMessage::class, $message);
        }

        $options = $message->getOptions()?->toArray() ?? [];
        $options['message'] = $message->getContent();
        $options['title'] = $message->getSubject();
        $options['token'] = $this->appToken;
        $options['user'] = $this->userKey;

        $endpoint = \sprintf('https://%s/1/messages.json', $this->getEndpoint());
        $response = $this->client->request('POST', $endpoint, [
            'body' => $options,
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote Pushover server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            throw new TransportException(\sprintf('Unable to send the Pushover push notification: "%s".', $response->getContent(false)), $response);
        }

        $result = $response->toArray(false);

        if (!isset($result['request'])) {
            throw new TransportException(\sprintf('Unable to find the message id within the Pushover response: "%s".', $response->getContent(false)), $response);
        }

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($result['request']);

        return $sentMessage;
    }
}
