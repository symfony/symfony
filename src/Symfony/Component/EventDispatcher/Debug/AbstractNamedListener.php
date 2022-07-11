<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\EventDispatcher\Debug;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @author Quentin Devos <quentin@devos.pm>
 */
abstract class AbstractNamedListener implements NamedListener
{
    protected string $name;
    protected string $pretty;
    protected ?string $callableRef = null;

    public function __construct(callable|array $listener, ?string $name)
    {
        if (\is_array($listener)) {
            [$this->name, $this->callableRef] = $this->parseListener($listener);
            $this->pretty = $this->name.'::'.$listener[1];
            $this->callableRef .= '::'.$listener[1];
        } elseif ($listener instanceof NamedListener) {
            $this->name = $listener->getName();
            $this->pretty = $listener->getPretty();
            $this->callableRef = $listener->getCallableRef();
        } elseif ($listener instanceof \Closure) {
            $r = new \ReflectionFunction($listener);
            if (str_contains($r->name, '{closure}')) {
                $this->pretty = $this->name = 'closure';
            } elseif ($class = $r->getClosureScopeClass()) {
                $this->name = $class->name;
                $this->pretty = $this->name.'::'.$r->name;
            } else {
                $this->pretty = $this->name = $r->name;
            }
        } elseif (\is_string($listener)) {
            $this->pretty = $this->name = $listener;
        } else {
            $this->name = get_debug_type($listener);
            $this->pretty = $this->name.'::__invoke';
            $this->callableRef = \get_class($listener).'::__invoke';
        }

        if (null !== $name) {
            $this->name = $name;
        }
    }

    private function parseListener(array $listener): array
    {
        if ($listener[0] instanceof \Closure) {
            foreach ((new \ReflectionFunction($listener[0]))->getAttributes(\Closure::class) as $attribute) {
                if ($name = $attribute->getArguments()['name'] ?? false) {
                    return [$name, $attribute->getArguments()['class'] ?? $name];
                }
            }
        }

        if ($listener[0] instanceof NamedListener) {
            return [$listener[0]->getName(), $listener[0]->getCallableRef()];
        }

        if (\is_object($listener[0])) {
            return [get_debug_type($listener[0]), \get_class($listener[0])];
        }

        return [$listener[0], $listener[0]];
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPretty(): string
    {
        return $this->pretty;
    }

    public function getCallableRef(): ?string
    {
        return $this->callableRef;
    }

    abstract public function __invoke(object $event, string $eventName, EventDispatcherInterface $dispatcher): void;
}
