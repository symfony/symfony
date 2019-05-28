<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Stamp;

use Symfony\Component\Messenger\Handler\HandlerDescriptor;

/**
 * Stamp identifying a message handled by the `HandleMessageMiddleware` middleware
 * and storing the handler returned value.
 *
 * @see \Symfony\Component\Messenger\Middleware\HandleMessageMiddleware
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 *
 * @experimental in 4.3
 */
final class HandledStamp implements StampInterface
{
    private $result;
    private $handlerName;

    /**
     * @param mixed $result The returned value of the message handler
     */
    public function __construct($result, string $handlerName)
    {
        $this->result = $result;
        $this->handlerName = $handlerName;
    }

    /**
     * @param mixed $result The returned value of the message handler
     */
    public static function fromDescriptor(HandlerDescriptor $handler, $result): self
    {
        return new self($result, $handler->getName());
    }

    /**
     * @return mixed
     */
    public function getResult()
    {
        return $this->result;
    }

    public function getHandlerName(): string
    {
        return $this->handlerName;
    }
}
