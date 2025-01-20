<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\RocketChat;

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
 * @author Jeroen Spee <https://github.com/Jeroeny>
 */
final class RocketChatTransport extends AbstractTransport
{
    protected const HOST = 'rocketchat.com';

    public function __construct(
        #[\SensitiveParameter] private string $accessToken,
        private ?string $chatChannel = null,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
    ) {
        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return \sprintf('rocketchat://%s%s', $this->getEndpoint(), null !== $this->chatChannel ? '?channel='.$this->chatChannel : '');
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof ChatMessage && (null === $message->getOptions() || $message->getOptions() instanceof RocketChatOptions);
    }

    /**
     * @see https://rocket.chat/docs/administrator-guides/integrations
     */
    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof ChatMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, ChatMessage::class, $message);
        }

        $options = $message->getOptions()?->toArray() ?? [];
        $options['channel'] ??= $message->getRecipientId() ?: $this->chatChannel;
        $options['text'] = $message->getSubject();

        $endpoint = \sprintf('https://%s/hooks/%s', $this->getEndpoint(), $this->accessToken);
        $response = $this->client->request('POST', $endpoint, [
            'json' => array_filter($options),
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote RocketChat server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            throw new TransportException(\sprintf('Unable to post the RocketChat message: %s.', $response->getContent(false)), $response);
        }

        $result = $response->toArray(false);
        if (!$result['success']) {
            throw new TransportException(\sprintf('Unable to post the RocketChat message: %s.', $result['error']), $response);
        }

        $success = $response->toArray(false);

        $sentMessage = new SentMessage($message, (string) $this);

        if (isset($success['message']['_id'])) {
            $sentMessage->setMessageId($success['message']['_id']);
        }

        return $sentMessage;
    }
}
