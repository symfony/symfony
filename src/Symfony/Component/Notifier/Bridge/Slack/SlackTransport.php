<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Slack;

use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Daniel Stancu <birkof@birkof.ro>
 *
 * @internal
 *
 * @experimental in 5.0
 */
final class SlackTransport extends AbstractTransport
{
    protected const HOST = 'hooks.slack.com';

    private $path;

    protected $client;

    public function __construct(string $path, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->path = $path;
        $this->client = $client;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('%s://%s/%s', SlackTransportFactory::SCHEME, $this->getEndpoint(), $this->path);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof ChatMessage && (null === $message->getOptions() || $message->getOptions() instanceof SlackOptions);
    }

    /**
     * Sending messages using Incoming Webhooks.
     *
     * @see https://api.slack.com/messaging/webhooks
     */
    protected function doSend(MessageInterface $message): void
    {
        if (!$message instanceof ChatMessage) {
            throw new LogicException(sprintf('The "%s" transport only supports instances of "%s" (instance of "%s" given).', __CLASS__, ChatMessage::class, get_debug_type($message)));
        }
        if ($message->getOptions() && !$message->getOptions() instanceof SlackOptions) {
            throw new LogicException(sprintf('The "%s" transport only supports instances of "%s" for options.', __CLASS__, SlackOptions::class));
        }

        if (!($opts = $message->getOptions()) && $notification = $message->getNotification()) {
            $opts = SlackOptions::fromNotification($notification);
        }

        $options = $opts ? $opts->toArray() : [];

        $options['text'] = $message->getSubject();
        $options['blocks'] = isset($options['blocks']) ? json_decode($options['blocks'], true) : null;

        $response = $this->client->request(
            'POST',
            sprintf('https://%s/%s', $this->getEndpoint(), $this->path),
            ['json' => array_filter($options)]
        );

        if (200 !== $response->getStatusCode()) {
            throw new TransportException(sprintf('Unable to post the Slack message: "%s".', $response->getContent(false)), $response);
        }

        $result = $response->getContent(false);
        if ('ok' !== $result) {
            throw new TransportException(sprintf('Unable to post the Slack message: "%s".', $result), $response);
        }
    }
}
