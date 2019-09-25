<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Transport;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\LegacyEventDispatcherProxy;
use Symfony\Component\Mailer\DelayedSmtpEnvelope;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\SmtpEnvelope;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\RawMessage;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 4.3
 */
abstract class AbstractTransport implements TransportInterface
{
    private $dispatcher;
    private $logger;
    private $rate = 0;
    private $lastSent = 0;

    public function __construct(EventDispatcherInterface $dispatcher = null, LoggerInterface $logger = null)
    {
        $this->dispatcher = LegacyEventDispatcherProxy::decorate($dispatcher);
        $this->logger = $logger ?: new NullLogger();
    }

    /**
     * Sets the maximum number of messages to send per second (0 to disable).
     */
    public function setMaxPerSecond(float $rate): self
    {
        if (0 >= $rate) {
            $rate = 0;
        }

        $this->rate = $rate;
        $this->lastSent = 0;

        return $this;
    }

    public function send(RawMessage $message, SmtpEnvelope $envelope = null): ?SentMessage
    {
        $message = clone $message;
        if (null !== $envelope) {
            $envelope = clone $envelope;
        } else {
            try {
                $envelope = new DelayedSmtpEnvelope($message);
            } catch (\Exception $e) {
                throw new TransportException('Cannot send message without a valid envelope.', 0, $e);
            }
        }

        if (null !== $this->dispatcher) {
            $event = new MessageEvent($message, $envelope);
            $this->dispatcher->dispatch($event);
            $envelope = $event->getEnvelope();
            $message = $event->getMessage();
        }

        if (!$envelope->getRecipients()) {
            return null;
        }

        $message = new SentMessage($message, $envelope);
        $this->doSend($message);

        $this->checkThrottling();

        return $message;
    }

    abstract protected function doSend(SentMessage $message): void;

    /**
     * @param Address[] $addresses
     *
     * @return string[]
     */
    protected function stringifyAddresses(array $addresses): array
    {
        return array_map(function (Address $a) {
            return $a->toString();
        }, $addresses);
    }

    protected function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    private function checkThrottling()
    {
        if (0 == $this->rate) {
            return;
        }

        $sleep = (1 / $this->rate) - (microtime(true) - $this->lastSent);
        if (0 < $sleep) {
            $this->logger->debug(sprintf('Email transport "%s" sleeps for %.2f seconds', __CLASS__, $sleep));
            usleep($sleep * 1000000);
        }
        $this->lastSent = microtime(true);
    }
}
