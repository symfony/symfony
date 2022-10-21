<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\GoogleChat;

use Symfony\Component\HttpClient\Exception\JsonException;
use Symfony\Component\Notifier\Exception\LogicException;
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
 * @author Jérôme Tamarelle <jerome@tamarelle.net>
 */
final class GoogleChatTransport extends AbstractTransport
{
    protected const HOST = 'chat.googleapis.com';

    private string $space;
    private string $accessKey;
    private string $accessToken;
    private ?string $threadKey;

    /**
     * @param string      $space       The space name the the webhook url "/v1/spaces/<space>/messages"
     * @param string      $accessKey   The "key" parameter of the webhook url
     * @param string      $accessToken The "token" parameter of the webhook url
     * @param string|null $threadKey   Opaque thread identifier string that can be specified to group messages into a single thread.
     *                                 If this is the first message with a given thread identifier, a new thread is created.
     *                                 Subsequent messages with the same thread identifier will be posted into the same thread.
     *                                 {@see https://developers.google.com/hangouts/chat/reference/rest/v1/spaces.messages/create#query-parameters}
     */
    public function __construct(string $space, string $accessKey, #[\SensitiveParameter] string $accessToken, string $threadKey = null, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->space = $space;
        $this->accessKey = $accessKey;
        $this->accessToken = $accessToken;
        $this->threadKey = $threadKey;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('googlechat://%s/%s%s',
            $this->getEndpoint(),
            $this->space,
            $this->threadKey ? '?thread_key='.urlencode($this->threadKey) : ''
        );
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof ChatMessage && (null === $message->getOptions() || $message->getOptions() instanceof GoogleChatOptions);
    }

    /**
     * @see https://developers.google.com/hangouts/chat/how-tos/webhooks
     */
    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof ChatMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, ChatMessage::class, $message);
        }

        if ($message->getOptions() && !$message->getOptions() instanceof GoogleChatOptions) {
            throw new LogicException(sprintf('The "%s" transport only supports instances of "%s" for options.', __CLASS__, GoogleChatOptions::class));
        }

        $opts = $message->getOptions();
        if (!$opts) {
            if ($notification = $message->getNotification()) {
                $opts = GoogleChatOptions::fromNotification($notification);
            } else {
                $opts = GoogleChatOptions::fromMessage($message);
            }
        }

        if (null !== $this->threadKey && null === $opts->getThreadKey()) {
            $opts->setThreadKey($this->threadKey);
        }

        $threadKey = $opts->getThreadKey() ?: $this->threadKey;

        $options = $opts->toArray();
        $url = sprintf('https://%s/v1/spaces/%s/messages?key=%s&token=%s%s',
            $this->getEndpoint(),
            $this->space,
            urlencode($this->accessKey),
            urlencode($this->accessToken),
            $threadKey ? '&threadKey='.urlencode($threadKey) : ''
        );
        $response = $this->client->request('POST', $url, [
            'json' => array_filter($options),
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote GoogleChat server.', $response, 0, $e);
        }

        try {
            $result = $response->toArray(false);
        } catch (JsonException $jsonException) {
            throw new TransportException('Unable to post the Google Chat message: Invalid response.', $response, $statusCode, $jsonException);
        }

        if (200 !== $statusCode) {
            throw new TransportException(sprintf('Unable to post the Google Chat message: "%s".', $result['error']['message'] ?? $response->getContent(false)), $response, $result['error']['code'] ?? $statusCode);
        }

        if (!\array_key_exists('name', $result)) {
            throw new TransportException(sprintf('Unable to post the Google Chat message: "%s".', $response->getContent(false)), $response);
        }

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($result['name']);

        return $sentMessage;
    }
}
