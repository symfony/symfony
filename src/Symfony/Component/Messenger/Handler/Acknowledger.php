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

use Symfony\Component\Messenger\Exception\LogicException;

/**
 * @author Nicolas Grekas <p@tchwork.com>
 */
class Acknowledger
{
    private $handlerClass;
    private $ack;
    private $error = null;
    private $result = null;

    /**
     * @param \Closure(\Throwable|null, mixed):void|null $ack
     */
    public function __construct(string $handlerClass, ?\Closure $ack = null)
    {
        $this->handlerClass = $handlerClass;
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
        return $this->error;
    }

    /**
     * @return mixed
     */
    public function getResult()
    {
        return $this->result;
    }

    public function isAcknowledged(): bool
    {
        return null === $this->ack;
    }

    public function __destruct()
    {
        if ($this->ack instanceof \Closure) {
            throw new LogicException(sprintf('The acknowledger was not called by the "%s" batch handler.', $this->handlerClass));
        }
    }

    private function doAck(?\Throwable $e = null, $result = null): void
    {
        if (!$ack = $this->ack) {
            throw new LogicException(sprintf('The acknowledger cannot be called twice by the "%s" batch handler.', $this->handlerClass));
        }
        $this->ack = null;
        $this->error = $e;
        $this->result = $result;
        $ack($e, $result);
    }
}
