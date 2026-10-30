<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Handler;

use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Exception\LogicException;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Acknowledger
{
    public readonly ClockInterface $clock;

    private ?\Closure $ack;
    private \Throwable|false|null $error = null;
    private mixed $result = null;

    /**
     * @param \Closure(\Throwable|null, mixed):void|null $ack
     * @param \Closure():bool|null                       $deferFlush Asks to flush the batch handler in a dispatch of its own, and returns false when it should flush the batch now
     */
    public function __construct(
        private string $handlerClass,
        ?\Closure $ack = null,
        ?ClockInterface $clock = null,
        private ?\Closure $deferFlush = null,
    ) {
        $this->clock = $clock ?? Clock::get();
        $this->ack = $ack ?? static function () {};
    }

    /**
     * @param mixed $result
     */
    public function ack($result = null): void
    {
        $this->doAck(null, $result);
    }

    public function nack(\Throwable $error): void
    {
        $this->doAck($error);
    }

    public function getError(): ?\Throwable
    {
        return $this->error ?: null;
    }

    public function getResult(): mixed
    {
        return $this->result;
    }

    public function isAcknowledged(): bool
    {
        return null === $this->ack;
    }

    /**
     * Asks to flush the batch handler in a dispatch of its own, right after the dispatch of the message.
     *
     * A batch handler can call this when its batch is full, so that processing the batch does not depend on the dispatch of the message that filled it.
     * Its flush() method is then called with $force set to true.
     *
     * @return bool Whether the batch handler will be flushed, false when it should flush the batch now
     */
    public function deferFlush(): bool
    {
        return null !== $this->deferFlush && ($this->deferFlush)();
    }

    public function __destruct()
    {
        if (null !== $this->ack) {
            throw new LogicException(\sprintf('The acknowledger was not called by the "%s" batch handler.', $this->handlerClass));
        }
    }

    private function doAck(?\Throwable $e = null, mixed $result = null): void
    {
        if (!($ack = $this->ack) || (null !== $this->error && (false !== $this->error || null === $e))) {
            throw new LogicException(\sprintf('The acknowledger cannot be called twice by the "%s" batch handler.', $this->handlerClass));
        }
        $this->error = $e ?: false;
        $this->result = $result;
        $ack($e, $result);
        $this->ack = null;
    }
}
