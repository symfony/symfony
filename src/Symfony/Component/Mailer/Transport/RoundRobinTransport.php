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

use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\SmtpEnvelope;
use Symfony\Component\Mime\RawMessage;

/**
 * Uses several Transports using a round robin algorithm.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @experimental in 4.3
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
            throw new TransportException(__CLASS__.' must have at least one transport configured.');
        }

        $this->transports = $transports;
        $this->deadTransports = new \SplObjectStorage();
        $this->retryPeriod = $retryPeriod;
    }

    public function send(RawMessage $message, SmtpEnvelope $envelope = null): ?SentMessage
    {
        while ($transport = $this->getNextTransport()) {
            try {
                return $transport->send($message, $envelope);
            } catch (TransportExceptionInterface $e) {
                $this->deadTransports[$transport] = microtime(true);
            }
        }

        throw new TransportException('All transports failed.');
    }

    /**
     * Rotates the transport list around and returns the first instance.
     */
    protected function getNextTransport(): ?TransportInterface
    {
        $cursor = $this->cursor;
        while (true) {
            $transport = $this->transports[$cursor];

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

    private function moveCursor(int $cursor): int
    {
        return ++$cursor >= \count($this->transports) ? 0 : $cursor;
    }
}
