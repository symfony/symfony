<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Transport;

use Symfony\Component\Notifier\Exception\InvalidArgumentException;
use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 5.2
 */
final class Transports implements TransportInterface
{
    private $transports;

    /**
     * @param TransportInterface[] $transports
     */
    public function __construct(iterable $transports)
    {
        $this->transports = [];
        foreach ($transports as $name => $transport) {
            $this->transports[$name] = $transport;
        }
    }

    public function __toString(): string
    {
        return '['.implode(',', array_keys($this->transports)).']';
    }

    public function supports(MessageInterface $message): bool
    {
        foreach ($this->transports as $transport) {
            if ($transport->supports($message)) {
                return true;
            }
        }

        return false;
    }

    public function send(MessageInterface $message): SentMessage
    {
        if (!$transport = $message->getTransport()) {
            foreach ($this->transports as $transportName => $transport) {
                if ($transport->supports($message)) {
                    return $transport->send($message);
                }
            }
            throw new LogicException(sprintf('None of the available transports support the given message (available transports: "%s").', implode('", "', array_keys($this->transports))));
        }

        if (!isset($this->transports[$transport])) {
            throw new InvalidArgumentException(sprintf('The "%s" transport does not exist (available transports: "%s").', $transport, implode('", "', array_keys($this->transports))));
        }

        if (!$this->transports[$transport]->supports($message)) {
            throw new LogicException(sprintf('The "%s" transport does not support the given message.', $transport));
        }

        return $this->transports[$transport]->send($message);
    }
}
