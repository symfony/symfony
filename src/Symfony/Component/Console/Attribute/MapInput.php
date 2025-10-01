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
            if (!$property->isPublic() || $property->isStatic()) {
                continue;
            }

            if ($argument = Argument::tryFrom($property)) {
                $self->definition[$property->name] = $argument;
                continue;
            }

            if ($option = Option::tryFrom($property)) {
                $self->definition[$property->name] = $option;
                continue;
            }

            if ($input = self::tryFrom($property)) {
                $self->definition[$property->name] = $input;
            }
        }

        if (!$self->definition) {
            throw new LogicException(\sprintf('The input class "%s" must have at least one argument or option.', $self->class->name));
        }

        return $self;
    }

    /**
     * @internal
     */
    public function resolveValue(InputInterface $input): mixed
    {
        $instance = $this->class->newInstanceWithoutConstructor();

        foreach ($this->definition as $name => $spec) {
            $instance->$name = $spec->resolveValue($input);
        }

        return $instance;
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
}
