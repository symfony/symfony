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

use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class SlackTransport extends AbstractTransport
{
    protected const HOST = 'slack.com';

    public function __construct(
        #[\SensitiveParameter] private string $accessToken,
        private ?string $channel = null,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
    ) {
        if (!preg_match('/^xox(b-|p-|a-2)/', $accessToken)) {
            throw new InvalidArgumentException('A valid Slack token needs to start with "xoxb-", "xoxp-" or "xoxa-2". See https://api.slack.com/authentication/token-types for further information.');
        }

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        $query = array_filter([
            'channel' => $this->channel,
        ]);

        return \sprintf('slack://%s%s', $this->getEndpoint(), $query ? '?'.http_build_query($query, '', '&') : '');
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof ChatMessage && (null === $message->getOptions() || $message->getOptions() instanceof SlackOptions);
    }

    /**
     * @see https://api.slack.com/methods/chat.postMessage
     */
    protected function doSend(MessageInterface $message): SlackSentMessage
    {
        if (!$message instanceof ChatMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, ChatMessage::class, $message);
        }

        if (!($options = $message->getOptions()) && $notification = $message->getNotification()) {
            $options = SlackOptions::fromNotification($notification);
        }

        $options = $options?->toArray() ?? [];
        $options['channel'] ??= $message->getRecipientId() ?: $this->channel;
        $options['text'] = $message->getSubject();

        $apiMethod = $message->getOptions() instanceof UpdateMessageSlackOptions ? 'chat.update' : 'chat.postMessage';
        if (\array_key_exists('post_at', $options)) {
            $apiMethod = 'chat.scheduleMessage';
        }

        $response = $this->client->request('POST', 'https://'.$this->getEndpoint().'/api/'.$apiMethod, [
            'json' => array_filter($options, function ($value): bool { return !\in_array($value, ['', [], null], true); }),
            'auth_bearer' => $this->accessToken,
            'headers' => [
                'Content-Type' => 'application/json; charset=utf-8',
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote Slack server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            throw new TransportException(\sprintf('Unable to post the Slack message: "%s".', $response->getContent(false)), $response);
        }

        $result = $response->toArray(false);
        if (!$result['ok']) {
            $errors = isset($result['errors']) ? ' ('.implode('|', $result['errors']).')' : '';

            throw new TransportException(\sprintf('Unable to post the Slack message: "%s"%s.', $result['error'], $errors), $response);
        }

        return new SlackSentMessage($message, (string) $this, $result['channel'], $result['ts']);
    }
}
