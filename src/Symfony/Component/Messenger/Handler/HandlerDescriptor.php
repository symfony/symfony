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

/**
 * Describes a handler and the possible associated options, such as `from_transport`, `bus`, etc.
 *
 * @author Samuel Roze <samuel.roze@gmail.com>
 */
final class HandlerDescriptor
{
    private $handler;
    private $name;
    private $batchHandler;
    private $options;

    public function __construct(callable $handler, array $options = [])
    {
        if (!$handler instanceof \Closure) {
            $handler = \Closure::fromCallable($handler);
        }

        $this->handler = $handler;
        $this->options = $options;

        $r = new \ReflectionFunction($handler);

        if (str_contains($r->name, '{closure}')) {
            $this->name = 'Closure';
        } elseif (!$handler = $r->getClosureThis()) {
            $class = \PHP_VERSION_ID >= 80111 ? $r->getClosureCalledClass() : $r->getClosureScopeClass();

            $this->name = ($class ? $class->name.'::' : '').$r->name;
        } else {
            if ($handler instanceof BatchHandlerInterface) {
                $this->batchHandler = $handler;
            }

            $this->name = \get_class($handler).'::'.$r->name;
        }
    }

    public function getHandler(): callable
    {
        return $this->handler;
    }

    public function getName(): string
    {
        $name = $this->name;
        $alias = $this->options['alias'] ?? null;

        if (null !== $alias) {
            $name .= '@'.$alias;
        }

        return $name;
    }

    public function getBatchHandler(): ?BatchHandlerInterface
    {
        return $this->batchHandler;
    }

    public function getOption(string $option)
    {
        return $this->options[$option] ?? null;
    }
}
