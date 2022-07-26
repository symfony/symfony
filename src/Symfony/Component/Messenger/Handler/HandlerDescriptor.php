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
    private \Closure $handler;
    private string $name;
    private ?BatchHandlerInterface $batchHandler = null;
    private array $options;

    public function __construct(callable $handler, array $options = [], BatchStrategyInterface $batchStrategy = null)
    {
        $handler = $handler(...);

        $this->handler = $handler;
        $this->options = $options;

        $r = new \ReflectionFunction($handler);

        if (str_contains($r->name, '{closure}')) {
            $this->name = 'Closure';
        } elseif (!$handler = $r->getClosureThis()) {
            $class = $r->getClosureScopeClass();

            $this->name = ($class ? $class->name.'::' : '').$r->name;
        } else {
            if ($handler instanceof BatchHandlerInterface) {
                $this->batchHandler = $handler;
            }

            $this->name = \get_class($handler).'::'.$r->name;
        }

        if (!$this->batchHandler && $r->isVariadic()) {
            $h = $this->handler;
            if (Result::class !== ($r->getParameters()[0] ?? null)?->getType()?->getName()) {
                $h = new ResultWrappedHandler($h);
            }

            if ($handler instanceof BatchStrategyProviderInterface) {
                $batchStrategy = $handler->getBatchStrategy();
            }

            $h = new BatchHandlerAdapter($h, $batchStrategy ?: new CountBatchStrategy(1));
            $this->batchHandler = $h;
            $this->handler = $h(...);
            unset($h);
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
