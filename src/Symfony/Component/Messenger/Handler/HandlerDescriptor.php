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

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Stamp\StampInterface;

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

    /**
     * @var array<string, array{class-string<Envelope|StampInterface>, bool, bool}>
     */
    private array $stampParameters = [];

    public function __construct(
        callable $handler,
        private array $options = [],
    ) {
        $handler = $handler(...);

        $this->handler = $handler;

        $r = new \ReflectionFunction($handler);

        if ($r->isAnonymous()) {
            $this->name = 'Closure';
        } elseif (!$handler = $r->getClosureThis()) {
            $class = $r->getClosureCalledClass();

            $this->name = ($class ? $class->name.'::' : '').$r->name;
        } else {
            if ($handler instanceof BatchHandlerInterface) {
                $this->batchHandler = $handler;
            }

            $this->name = $handler::class.'::'.$r->name;
        }

        foreach (\array_slice($r->getParameters(), 1) as $parameter) {
            $type = $parameter->getType();

            if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }
            $class = $type->getName();

            if (Envelope::class === $class || is_subclass_of($class, StampInterface::class)) {
                $this->stampParameters[$parameter->name] = [$class, $type->allowsNull(), $parameter->isDefaultValueAvailable()];
            }
        }
    }

    public function getHandler(): \Closure
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

    public function getOption(string $option): mixed
    {
        return $this->options[$option] ?? null;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Returns the parameters of the handler typed with Envelope or with a stamp class, keyed by name.
     *
     * The first parameter, which receives the message, is never listed. Each entry holds
     * the class, whether the parameter accepts null and whether it has a default value.
     *
     * @internal
     *
     * @return array<string, array{class-string<Envelope|StampInterface>, bool, bool}>
     */
    public function getStampParameters(): array
    {
        return $this->stampParameters;
    }
}
