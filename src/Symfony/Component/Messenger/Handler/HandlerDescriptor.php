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
    private $options;

    public function __construct(callable $handler, array $options = [])
    {
        $this->handler = $handler;
        $this->options = $options;
    }

    public function getHandler(): callable
    {
        return $this->handler;
    }

    public function getName(): string
    {
        $name = $this->callableName($this->handler);
        $alias = $this->options['alias'] ?? null;

        if (null !== $alias) {
            $name .= '@'.$alias;
        }

        return $name;
    }

    public function getOption(string $option)
    {
        return $this->options[$option] ?? null;
    }

    private function callableName(callable $handler): string
    {
        if (\is_array($handler)) {
            if (\is_object($handler[0])) {
                return \get_class($handler[0]).'::'.$handler[1];
            }

            return $handler[0].'::'.$handler[1];
        }

        if (\is_string($handler)) {
            return $handler;
        }

        if ($handler instanceof \Closure) {
            $r = new \ReflectionFunction($handler);
            if (str_contains($r->name, '{closure}')) {
                return 'Closure';
            }
            if ($class = $r->getClosureScopeClass()) {
                return $class->name.'::'.$r->name;
            }

            return $r->name;
        }

        return \get_class($handler).'::__invoke';
    }
}
