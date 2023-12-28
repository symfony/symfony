<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\Pusher;

use Pusher\Pusher;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\PushMessage;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * @author Yasmany Cubela Medina <yasmanycm@gmail.com>
 */
final class PusherTransport extends AbstractTransport
{
    public function __construct(
        private readonly Pusher $pusher,
        HttpClientInterface $client = null,
        EventDispatcherInterface $dispatcher = null,
    ) {

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        $settings = $this->pusher->getSettings();
        preg_match('/api-([\w]+)\.pusher\.com$/m', $settings['host'], $server);

        return sprintf('pusher://%s?server=%s', $settings['app_id'], $server[1]);
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof PushMessage && (null === $message->getOptions() || $message->getOptions() instanceof PusherOptions);
    }

    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$message instanceof PushMessage) {
            throw new UnsupportedMessageTypeException(__CLASS__, PushMessage::class, $message);
        }

        $options = $message->getOptions();

        if (!$options instanceof PusherOptions) {
            throw new LogicException(sprintf('The "%s" transport only supports instances of "%s" for options.', __CLASS__, PusherOptions::class));
        }

        try {
            $this->pusher->trigger($options->getChannels(), $message->getSubject(), $message->getContent(), [], true);
        } catch (Throwable) {
            throw new \RuntimeException('An error occurred at Pusher Notifier Transport.');
        }

        return new SentMessage($message, $this->__toString());
    }
}
