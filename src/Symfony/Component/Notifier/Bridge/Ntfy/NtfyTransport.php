<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Ntfy;

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
 * @author Mickael Perraud <mikaelkael.fr@gmail.com>
 */
final class NtfyTransport extends AbstractTransport
{
    protected const HOST = 'ntfy.sh';
    private ?string $user = null;
    private ?string $password = null;

    public function __construct(
        private string $topic,
        private bool $secureHttp = true,
        ?HttpClientInterface $client = null,
        ?EventDispatcherInterface $dispatcher = null,
    ) {
        parent::__construct($client, $dispatcher);
    }

    public function getTopic(): string
    {
        return $this->topic;
    }

    public function setPassword(#[\SensitiveParameter] ?string $password): self
    {
        $this->password = $password;

        return $this;
    }

    public function setUser(?string $user): self
    {
        $this->user = $user;

        return $this;
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof PushMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, PushMessage::class, $message);
        }

        if ($message->getOptions() && !$message->getOptions() instanceof NtfyOptions) {
            throw new LogicException(\sprintf('The "%s" transport only supports instances of "%s" for options.', __CLASS__, NtfyOptions::class));
        }

        if (!($opts = $message->getOptions()) && $notification = $message->getNotification()) {
            $opts = NtfyOptions::fromNotification($notification);
        }

        $options = $opts ? $opts->toArray() : [];

        $options['topic'] = $this->getTopic();

        if (!isset($options['title'])) {
            $options['title'] = $message->getSubject();
        }
        if (!isset($options['message'])) {
            $options['message'] = $message->getContent();
        }

        $headers = [];

        if (null !== $this->user && null !== $this->password) {
            $headers['Authorization'] = 'Basic '.rtrim(base64_encode($this->user.':'.$this->password), '=');
        } elseif (null !== $this->password) {
            $headers['Authorization'] = 'Bearer '.$this->password;
        }

        $response = $this->client->request('POST', ($this->secureHttp ? 'https' : 'http').'://'.$this->getEndpoint(), [
            'headers' => $headers,
            'json' => $options,
        ]);

        try {
            $statusCode = $response->getStatusCode();
        } catch (TransportExceptionInterface $e) {
            throw new TransportException('Could not reach the remote Ntfy server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            throw new TransportException(\sprintf('Unable to send the Ntfy push notification: "%s".', $response->getContent(false)), $response);
        }

        $result = $response->toArray(false);

        if (empty($result['id'])) {
            throw new TransportException(\sprintf('Unable to send the Ntfy push notification: "%s".', $response->getContent(false)), $response);
        }

        $sentMessage = new SentMessage($message, (string) $this);
        $sentMessage->setMessageId($result['id']);

        return $sentMessage;
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof PushMessage
            && (null === $message->getOptions() || $message->getOptions() instanceof NtfyOptions);
    }

    public function __toString(): string
    {
        return \sprintf('ntfy://%s/%s', $this->getEndpoint(), $this->getTopic());
    }
}
