<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Mattermost;

use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Emanuele Panzeri <thepanz@gmail.com>
 */
final class MattermostTransport extends AbstractTransport
{
    public function __construct(
        #[\SensitiveParameter] private string $token,
        private string $channel,
        private ?string $path = null,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
    ) {
        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return \sprintf('mattermost://%s?channel=%s', $this->getEndpoint(), $this->channel);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof ChatMessage && (null === $message->getOptions() || $message->getOptions() instanceof MattermostOptions);
    }

    /**
     * @see https://api.mattermost.com
     */
    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof ChatMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, ChatMessage::class, $message);
        }

        $options = $message->getOptions()?->toArray() ?? [];
        $options['message'] = $message->getSubject();
        $options['channel_id'] ??= $message->getRecipientId() ?: $this->channel;

        $endpoint = \sprintf('https://%s/api/v4/posts', $this->getEndpoint());

        $response = $this->client->request('POST', $endpoint, [
            'auth_bearer' => $this->token,
            'json' => array_filter($options),
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote Mattermost server.', $response, 0, $e);
        }

        if (201 !== $statusCode) {
            $result = $response->toArray(false);

            throw new TransportException(\sprintf('Unable to post the Mattermost message: %s (%s).', $result['message'], $result['id']), $response);
        }

        $success = $response->toArray(false);

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($success['id']);

        return $sentMessage;
    }

    protected function getEndpoint(): string
    {
        return rtrim($this->host.($this->port ? ':'.$this->port : '').($this->path ?? ''), '/');
    }
}
