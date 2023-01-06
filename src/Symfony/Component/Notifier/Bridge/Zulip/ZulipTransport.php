<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Zulip;

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
 * @author Mohammad Emran Hasan <phpfour@gmail.com>
 */
final class ZulipTransport extends AbstractTransport
{
    private string $email;
    private string $token;
    private string $channel;

    public function __construct(string $email, #[\SensitiveParameter] string $token, string $channel, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->email = $email;
        $this->token = $token;
        $this->channel = $channel;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('zulip://%s?channel=%s', $this->getEndpoint(), $this->channel);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof ChatMessage && (null === $message->getOptions() || $message->getOptions() instanceof ZulipOptions);
    }

    /**
     * @see https://zulipchat.com/api/send-message
     */
    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof ChatMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, ChatMessage::class, $message);
        }

        if (null !== $message->getOptions() && !($message->getOptions() instanceof ZulipOptions)) {
            throw new LogicException(sprintf('The "%s" transport only supports instances of "%s" for options.', __CLASS__, ZulipOptions::class));
        }

        $options = ($opts = $message->getOptions()) ? $opts->toArray() : [];
        $options['content'] = $message->getSubject();

        if (null === $message->getRecipientId() && empty($options['topic'])) {
            throw new LogicException(sprintf('The "%s" transport requires a topic when posting to streams.', __CLASS__));
        }

        if (null === $message->getRecipientId()) {
            $options['type'] = 'stream';
            $options['to'] = $this->channel;
        } else {
            $options['type'] = 'private';
            $options['to'] = $message->getRecipientId();
        }

        $endpoint = sprintf('https://%s/api/v1/messages', $this->getEndpoint());

        $response = $this->client->request('POST', $endpoint, [
            'auth_basic' => $this->email.':'.$this->token,
            'body' => $options,
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote Zulip server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            $result = $response->toArray(false);

            throw new TransportException(sprintf('Unable to post the Zulip message: "%s" (%s).', $result['msg'], $result['code']), $response);
        }

        $success = $response->toArray(false);

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($success['id']);

        return $sentMessage;
    }
}
