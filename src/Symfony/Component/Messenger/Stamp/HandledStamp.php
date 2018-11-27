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

/**
 * Stamp identifying a message handled by the `HandleMessageMiddleware` middleware
 * and storing the handler returned value.
 *
 * @see \Symfony\Component\Messenger\Middleware\HandleMessageMiddleware
 *
 * @author Maxime Steinhausser <maxime.steinhausser@gmail.com>
 *
 * @experimental in 4.2
 */
final class HandledStamp implements StampInterface
{
    private $result;
    private $callableName;
    private $handlerAlias;

    /**
     * @param mixed $result The returned value of the message handler
     */
    public function __construct($result, string $callableName, string $handlerAlias = null)
    {
        $this->result = $result;
        $this->callableName = $callableName;
        $this->handlerAlias = $handlerAlias;
    }

    /**
     * @param mixed $result The returned value of the message handler
     */
    public static function fromCallable(callable $handler, $result, string $handlerAlias = null): self
    {
        if (\is_array($handler)) {
            if (\is_object($handler[0])) {
                return new self($result, \get_class($handler[0]).'::'.$handler[1], $handlerAlias);
            }

            return new self($result, $handler[0].'::'.$handler[1], $handlerAlias);
        }

        if (\is_string($handler)) {
            return new self($result, $handler, $handlerAlias);
        }

        if ($handler instanceof \Closure) {
            $r = new \ReflectionFunction($handler);
            if (false !== strpos($r->name, '{closure}')) {
                return new self($result, 'Closure', $handlerAlias);
            }
            if ($class = $r->getClosureScopeClass()) {
                return new self($result, $class->name.'::'.$r->name, $handlerAlias);
            }

            return new self($result, $r->name, $handlerAlias);
        }

        return new self($result, \get_class($handler).'::__invoke', $handlerAlias);
    }

    /**
     * @return mixed
     */
    public function getResult()
    {
        return $this->result;
    }

    public function getCallableName(): string
    {
        return $this->callableName;
    }

    public function getHandlerAlias(): ?string
    {
        return $this->handlerAlias;
    }
}
