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
use Symfony\Component\Notifier\Message\MessageInterface;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 5.0
 */
final class Transports implements TransportInterface
{
    private $transports;
    private $default;

    /**
     * @param TransportInterface[] $transports
     */
    public function __construct(iterable $transports)
    {
        $this->transports = [];
        foreach ($transports as $name => $transport) {
            if (null === $this->default) {
                $this->default = $transport;
            }
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

    public function send(MessageInterface $message): void
    {
        if (!$transport = $message->getTransport()) {
            $this->default->send($message);

            return;
        }

        if (!isset($this->transports[$transport])) {
            throw new InvalidArgumentException(sprintf('The "%s" transport does not exist.', $transport));
        }

        $this->transports[$transport]->send($message);
    }
}
