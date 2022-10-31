<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Chatwork;

use Symfony\Component\Notifier\Exception\TransportException;
use Symfony\Component\Notifier\Exception\TransportExceptionInterface;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Ippei Sumida <ippey.s@gmail.com>
 */
class ChatworkTransport extends AbstractTransport
{
    protected const HOST = 'api.chatwork.com';

    private string $apiToken;
    private string $roomId;

    public function __construct(#[\SensitiveParameter] string $apiToken, string $roomId, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->apiToken = $apiToken;
        $this->roomId = $roomId;
        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('chatwork://%s?room_id=%s', $this->getEndpoint(), $this->roomId);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof ChatMessage && (null === $message->getOptions() || $message->getOptions() instanceof ChatworkOptions);
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof ChatMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, ChatMessage::class, $message);
        }

        $messageOptions = $message->getOptions();
        $options = $messageOptions ? $messageOptions->toArray() : [];

        $bodyBuilder = new ChatworkMessageBodyBuilder();
        if (\array_key_exists('to', $options)) {
            $bodyBuilder->to($options['to']);
        }
        if (\array_key_exists('selfUnread', $options)) {
            $bodyBuilder->selfUnread($options['selfUnread']);
        }

        $messageBody = $bodyBuilder
            ->body($message->getSubject())
            ->getMessageBody();

        $endpoint = sprintf('https://%s/v2/rooms/%s/messages', $this->getEndpoint(), $this->roomId);
        $response = $this->client->request('POST', $endpoint, [
            'body' => $messageBody,
            'headers' => [
                'X-ChatWorkToken' => $this->apiToken,
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote Chatwork server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            $originalContent = $message->getSubject();
            $result = $response->toArray(false);
            $errors = $result['errors'];
            throw new TransportException(sprintf('Unable to post the Chatwork message: "%s" (%d: %s).', $originalContent, $statusCode, implode(', ', $errors)), $response);
        }

        return new SentMessage($message, (string) $this);
    }
}
