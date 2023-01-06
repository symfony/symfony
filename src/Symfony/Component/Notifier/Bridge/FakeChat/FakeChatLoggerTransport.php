<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\FakeChat;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\ChatMessage;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Antoine Makdessi <amakdessi@me.com>
 */
final class FakeChatLoggerTransport extends AbstractTransport
{
    protected const HOST = 'default';

    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null)
    {
        $this->logger = $logger;

        parent::__construct($client, $dispatcher);
    }

    public function __toString(): string
    {
        return sprintf('fakechat+logger://%s', $this->getEndpoint());
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof ChatMessage;
    }

    /**
     * @param MessageInterface|ChatMessage $message
     */
    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$this->supports($message)) {
            throw new UnsupportedMessageTypeException(__CLASS__, ChatMessage::class, $message);
        }

        $subject = 'New Chat message without specified recipient!';
        if (null !== $message->getRecipientId()) {
            $subject = sprintf('New Chat message for recipient: %s', $message->getRecipientId());
        }

        $this->logger->info(sprintf('%s: %s', $subject, $message->getSubject()));

        return new SentMessage($message, (string) $this);
    }
}
