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
 * Send messages via Slack using Slack Incoming Webhooks.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Daniel Stancu <birkof@birkof.ro>
 *
 * @internal
 *
 * @see https://api.slack.com/messaging/webhooks
 *
 * @experimental in 5.1
 */
final class SlackTransport extends AbstractTransport
{
    protected const HOST = 'hooks.slack.com';

    private $id;

    /**
     * @param string $id The hook id (anything after https://hooks.slack.com/services/)
     */
    public function __construct(string $id, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->id = $id;
        $this->client = $client;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('slack://%s/%s', $this->getEndpoint(), $this->id);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof ChatMessage && (null === $message->getOptions() || $message->getOptions() instanceof SlackOptions);
    }

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
        $id = $message->getRecipientId() ?: $this->id;
        $options['text'] = $message->getSubject();
        $response = $this->client->request('POST', sprintf('https://%s/services/%s', $this->getEndpoint(), $id), [
            'json' => array_filter($options),
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new TransportException('Unable to post the Slack message: '.$response->getContent(false), $response);
        }

        $result = $response->getContent(false);
        if ('ok' !== $result) {
            throw new TransportException('Unable to post the Slack message: '.$result, $response);
        }
    }
}
