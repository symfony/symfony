<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\PagerDuty;

use Symfony\Component\Notifier\Exception\LogicException;
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
 * @author Joseph Bielawski <stloyd@gmail.com>
 */
final class PagerDutyTransport extends AbstractTransport
{
    protected const HOST = 'events.pagerduty.com';

    public function __construct(#[\SensitiveParameter] private readonly string $token, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('pagerduty://%s', $this->getEndpoint());
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof PushMessage;
    }

    protected function doSend(MessageInterface $message = null): SentMessage
    {
        if (!$message instanceof PushMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, PushMessage::class, $message);
        }

        if (null !== $message->getOptions() && !($message->getOptions() instanceof PagerDutyOptions)) {
            throw new LogicException(sprintf('The "%s" transport only supports instances of "%s" for options.', __CLASS__, PagerDutyOptions::class));
        }

        $body = ($opts = $message->getOptions()) ? $opts->toArray() : [];
        $body['payload']['summary'] = $message->getContent();
        $body['payload']['source'] = $message->getSubject();

        $response = $this->client->request('POST', 'https://'.$this->getEndpoint().'/v2/enqueue', [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => $this->token,
            ],
            'json' => $body,
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote PagerDuty server.', $response, 0, $e);
        }

        $result = $response->toArray(false);

        if (202 !== $statusCode) {
            throw new TransportException(sprintf('Unable to post the PagerDuty message: "%s".', $result['error']['message']), $response);
        }

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($result['dedup_key'] ?? $message->getRecipientId());

        return $sentMessage;
    }
}
