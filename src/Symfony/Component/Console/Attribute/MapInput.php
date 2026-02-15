<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Attribute;

use Symfony\Component\Console\Attribute\Reflection\ReflectionMember;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Interaction\Interaction;

/**
 * Maps a command input into an object (DTO).
 */
#[\Attribute(\Attribute::TARGET_PARAMETER | \Attribute::TARGET_PROPERTY)]
final class MapInput
{
    /**
     * @var array<string, Argument|Option|self>
     */
    private array $definition = [];

    private \ReflectionClass $class;

    /**
     * @var list<Interact>
     */
    private array $interactiveAttributes = [];

    /**
     * @internal
     */
    public static function tryFrom(\ReflectionParameter|\ReflectionProperty $member): ?self
    {
        $reflection = new ReflectionMember($member);

        if (!$self = $reflection->getAttribute(self::class)) {
            return null;
        }

        $type = $reflection->getType();

        if (!$type instanceof \ReflectionNamedType) {
            throw new LogicException(\sprintf('The input %s "%s" must have a named type.', $reflection->getMemberName(), $member->name));
        }

        if (!class_exists($class = $type->getName())) {
            throw new LogicException(\sprintf('The input class "%s" does not exist.', $type->getName()));
        }

        $self->class = new \ReflectionClass($class);

        foreach ($self->class->getProperties() as $property) {
            if ($argument = Argument::tryFrom($property)) {
                $self->definition[$property->name] = $argument;
            } elseif ($option = Option::tryFrom($property)) {
                $self->definition[$property->name] = $option;
            } elseif ($input = self::tryFrom($property)) {
                $self->definition[$property->name] = $input;
            }

            if (isset($self->definition[$property->name]) && (!$property->isPublic() || $property->isStatic())) {
                throw new LogicException(\sprintf('The input property "%s::$%s" must be public and non-static.', $self->class->name, $property->name));
            }
        }

        if (!$self->definition) {
            throw new LogicException(\sprintf('The input class "%s" must have at least one argument or option.', $self->class->name));
        }

        foreach ($self->class->getMethods() as $method) {
            if ($attribute = Interact::tryFrom($method)) {
                $self->interactiveAttributes[] = $attribute;
            }
        }

        return $self;
    }

    /**
     * @internal
     */
    public function setValue(InputInterface $input, object $object): void
    {
        foreach ($this->definition as $name => $spec) {
            $property = $this->class->getProperty($name);

            if (!$property->isInitialized($object) || \in_array($value = $property->getValue($object), [null, []], true)) {
                continue;
            }

            match (true) {
                $spec instanceof Argument => $input->setArgument($spec->name, $value),
                $spec instanceof Option => $input->setOption($spec->name, $value),
                $spec instanceof self => $spec->setValue($input, $value),
                default => throw new LogicException('Unexpected specification type.'),
            };
        }
    }

    /**
     * @return iterable<Argument>
     */
    public function getArguments(): iterable
    {
        foreach ($this->definition as $spec) {
            if ($spec instanceof Argument) {
                yield $spec;
            } elseif ($spec instanceof self) {
                yield from $spec->getArguments();
            }
        }
    }

    /**
     * @return iterable<Option>
     */
    public function getOptions(): iterable
    {
        foreach ($this->definition as $spec) {
            if ($spec instanceof Option) {
                yield $spec;
            } elseif ($spec instanceof self) {
                yield from $spec->getOptions();
            }
        }
    }

    /**
     * @internal
     *
     * @return \ReflectionClass<object>
     */
    public function getClass(): \ReflectionClass
    {
        return $this->class;
    }

    /**
     * @internal
     *
     * @return array<string, Argument|Option|self>
     */
    public function getDefinition(): array
    {
        return $this->definition;
    }

    /**
     * Creates a populated instance of the DTO from command input.
     *
     * @internal
     */
    public function createInstance(InputInterface $input): object
    {
        $instance = $this->class->newInstanceWithoutConstructor();

        foreach ($this->definition as $name => $spec) {
            if ($spec instanceof Argument) {
                $value = $input->getArgument($spec->name);
                if ($spec->isRequired() && \in_array($value, [null, []], true)) {
                    continue;
                }
                $instance->$name = $this->resolveValue($spec->typeName, $value, $spec->default);
            } elseif ($spec instanceof Option) {
                $value = $input->getOption($spec->name);
                $instance->$name = $this->resolveValue($spec->typeName, $value, $spec->default);
            } elseif ($spec instanceof self) {
                $instance->$name = $spec->createInstance($input);
            }
        }

        return $instance;
    }

    private function resolveValue(string $typeName, mixed $value, mixed $default): mixed
    {
        if (null === $value) {
            return $default;
        }

        if ('' === $value) {
            return $value;
        }

        if (is_subclass_of($typeName, \BackedEnum::class)) {
            return $value instanceof $typeName ? $value : $typeName::tryFrom($value);
        }

        if (is_a($typeName, \DateTimeInterface::class, true)) {
            if ($value instanceof \DateTimeInterface) {
                return $value;
            }
            $class = \DateTimeInterface::class === $typeName ? \DateTimeImmutable::class : $typeName;

            return new $class($value);
        }

        return $value;
    }

    /**
     * @internal
     *
     * @return iterable<Interaction>
     */
    public function getPropertyInteractions(): iterable
    {
        foreach ($this->definition as $spec) {
            if ($spec instanceof self) {
                yield from $spec->getPropertyInteractions();
            } elseif ($spec instanceof Argument && $attribute = $spec->getInteractiveAttribute()) {
                yield new Interaction($this, $attribute);
            }
        }
    }

    /**
     * @internal
     *
     * @return iterable<Interaction>
     */
    public function getMethodInteractions(): iterable
    {
        foreach ($this->definition as $spec) {
            if ($spec instanceof self) {
                yield from $spec->getMethodInteractions();
            }
        }

        foreach ($this->interactiveAttributes as $attribute) {
            yield new Interaction($this, $attribute);
        }
    }
}
