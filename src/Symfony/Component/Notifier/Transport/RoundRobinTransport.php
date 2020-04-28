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

use Symfony\Component\Notifier\Exception\LogicException;
use Symfony\Component\Notifier\Exception\RuntimeException;
use Symfony\Component\Notifier\Exception\TransportExceptionInterface;
use Symfony\Component\Notifier\Message\MessageInterface;
use Symfony\Component\Notifier\Message\SentMessage;

/**
 * Uses several Transports using a round robin algorithm.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 5.1
 */
class RoundRobinTransport implements TransportInterface
{
    private $deadTransports;
    private $transports = [];
    private $retryPeriod;
    private $cursor = 0;

    /**
     * @param TransportInterface[] $transports
     */
    public function __construct(array $transports, int $retryPeriod = 60)
    {
        if (!$transports) {
            throw new LogicException(sprintf('"%s" must have at least one transport configured.', static::class));
        }

        $this->transports = $transports;
        $this->deadTransports = new \SplObjectStorage();
        $this->retryPeriod = $retryPeriod;
        // the cursor initial value is randomized so that
        // when are not in a daemon, we are still rotating the transports
        $this->cursor = mt_rand(0, \count($transports) - 1);
    }

    public function __toString(): string
    {
        return implode(' '.$this->getNameSymbol().' ', array_map('strval', $this->transports));
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
        while ($transport = $this->getNextTransport($message)) {
            try {
                return $transport->send($message);
            } catch (TransportExceptionInterface $e) {
                $this->deadTransports[$transport] = microtime(true);
            }
        }

        throw new RuntimeException('All transports failed.');
    }

    /**
     * Rotates the transport list around and returns the first instance.
     */
    protected function getNextTransport(MessageInterface $message): ?TransportInterface
    {
        $cursor = $this->cursor;
        while (true) {
            $transport = $this->transports[$cursor];

            if (!$transport->supports($message)) {
                $cursor = $this->moveCursor($cursor);
                continue;
            }

            if (!$this->isTransportDead($transport)) {
                break;
            }

            if ((microtime(true) - $this->deadTransports[$transport]) > $this->retryPeriod) {
                $this->deadTransports->detach($transport);

                break;
            }

            if ($this->cursor === $cursor = $this->moveCursor($cursor)) {
                return null;
            }
        }

        $this->cursor = $this->moveCursor($cursor);

        return $transport;
    }

    protected function isTransportDead(TransportInterface $transport): bool
    {
        return $this->deadTransports->contains($transport);
    }

    protected function getNameSymbol(): string
    {
        return '&&';
    }

    private function moveCursor(int $cursor): int
    {
        return ++$cursor >= \count($this->transports) ? 0 : $cursor;
    }
}
