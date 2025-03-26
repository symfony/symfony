<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Matrix;

use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Exception\UnsupportedOptionsException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * @author Frank Schulze <frank@akiber.de>
 */
final class MatrixTransport extends AbstractTransport
{
    // not all Message Types are supported by Matrix API
    private const SUPPORTED_MSG_TYPES_BY_API = ['m.text', 'm.emote', 'm.notice', 'm.image', 'm.file', 'm.audio', 'm.video', 'm.key.verification'];

    public function __construct(
        #[\SensitiveParameter] private string $accessToken,
        private bool $ssl,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
    ) {
        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return \sprintf('matrix://%s', $this->getEndpoint(false));
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof ChatMessage && (null === $message->getOptions() || $message->getOptions() instanceof MatrixOptions);
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof ChatMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, ChatMessage::class, $message);
        }

        if (($opts = $message->getOptions()) && !$message->getOptions() instanceof MatrixOptions) {
            throw new UnsupportedOptionsException(__CLASS__, MatrixOptions::class, $opts);
        }

        $options = $opts ? $opts->toArray() : [];

        $options['msgtype'] = $options['msgtype'] ?? 'm.text';

        if (!\in_array($options['msgtype'], self::SUPPORTED_MSG_TYPES_BY_API, true)) {
            throw new LogicException(\sprintf('Unsupported message type: "%s". Only "%s" are supported by Matrix Client-Server API v3.', $options['msgtype'], implode(', ', self::SUPPORTED_MSG_TYPES_BY_API)));
        }

        if (null === $message->getRecipientId()) {
            throw new LogicException('Recipient id is required.');
        }

        $recipient = match ($message->getRecipientId()[0]) {
            '@' => $this->getDirectMessageChannel($message->getRecipientId()),
            '!' => $message->getRecipientId(),
            '#' => $this->getRoomFromAlias($message->getRecipientId()),
            default => throw new LogicException(\sprintf('Only recipients starting with "!","@","#" are supported ("%s" given).', $message->getRecipientId()[0])),
        };

        $options['body'] = $message->getSubject();
        if ('org.matrix.custom.html' === $options['format']) {
            $options['formatted_body'] = $message->getSubject();
            $options['body'] = strip_tags($message->getSubject());
        }

        $response = $this->request('PUT', \sprintf('/_matrix/client/v3/rooms/%s/send/%s/%s', $recipient, 'm.room.message', Uuid::v4()), ['json' => $options]);

        $success = $response->toArray(false);
        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($success['event_id']);

        return $sentMessage;
    }

    protected function getEndpoint(bool $full = false): string
    {
        return rtrim(($full ? $this->getScheme().'://' : '').$this->host.($this->port ? ':'.$this->port : ''), '/');
    }

    private function getRoomFromAlias(string $alias): string
    {
        $response = $this->request('GET', \sprintf('/_matrix/client/v3/directory/room/%s', urlencode($alias)));

        return $response->toArray()['room_id'];
    }

    private function createPrivateChannel(string $recipientId): ?array
    {
        $invites[] = $recipientId;
        $response = $this->request('POST', '/_matrix/client/v3/createRoom', ['json' => ['creation_content' => ['m.federate' => false], 'is_direct' => true, 'preset' => 'trusted_private_chat', 'invite' => $invites]]);

        return $response->toArray();
    }

    private function getDirectMessageChannel(string $recipientId): ?string
    {
        $response = $this->getAccountData($this->getWhoami()['user_id'], 'm.direct');
        if (!isset($response[$recipientId])) {
            $roomid = $this->createPrivateChannel($recipientId)['room_id'];
            $response[$recipientId] = [$roomid];
            $this->updateAccountData($this->getWhoami()['user_id'], 'm.direct', $response);

            return $roomid;
        }

        return $response[$recipientId][0];
    }

    private function updateAccountData(string $userId, string $type, array $data): void
    {
        $response = $this->request('PUT', \sprintf('/_matrix/client/v3/user/%s/account_data/%s', urlencode($userId), $type), ['json' => $data]);
        if ([] !== $response->toArray()) {
            throw new TransportException('Unable to update account data.', $response);
        }
    }

    private function getAccountData(string $userId, string $type): ?array
    {
        $response = $this->request('GET', \sprintf('/_matrix/client/v3/user/%s/account_data/%s', urlencode($userId), $type));

        return $response->toArray();
    }

    private function getWhoami(): ?array
    {
        $response = $this->request('GET', '/_matrix/client/v3/account/whoami');

        return $response->toArray();
    }

    private function getScheme(): string
    {
        return $this->ssl ? 'https' : 'http';
    }

    private function request(string $method, string $uri, ?array $options = []): ResponseInterface
    {
        $options += [
            'auth_bearer' => $this->accessToken,
        ];
        $response = $this->client->request($method, $this->getEndpoint(true).$uri, $options);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportException $e) {
            throw new TransportException('Could not reach the Matrix server.', $response, 0, $e);
        }

        if (\in_array($statusCode, [400, 403, 405])) {
            $result = $response->toArray(false);
            throw new TransportException(\sprintf('Error: Matrix responded with "%s (%s)"', $result['error'], $result['errcode']), $response);
        }

        return $response;
    }
}
