<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Bridge\FakeSms;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Notifier\Exception\UnsupportedMessageTypeException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\Transport\AbstractTransport;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Antoine Makdessi <amakdessi@me.com>
 */
final class FakeSmsLoggerTransport extends AbstractTransport
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
        return sprintf('fakesms+logger://%s', $this->getEndpoint());
    }

    public function supports(MessageInterface $message): bool
    {
        return $message instanceof SmsMessage;
    }

    /**
     * @param MessageInterface|SmsMessage $message
     */
    protected function doSend(MessageInterface $message): SentMessage
    {
        if (!$this->supports($message)) {
            throw new UnsupportedMessageTypeException(__CLASS__, SmsMessage::class, $message);
        }

        $this->logger->info(sprintf('New SMS on phone number: %s', $message->getPhone()));

        return new SentMessage($message, (string) $this);
    }
}
