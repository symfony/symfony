<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\ArgumentResolver\ValueResolver;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\MapInput;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Attribute\Reflection\ReflectionMember;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Resolves the value of a input argument/option to an object holding the #[MapInput] attribute.
 *
 * @author Yonel Ceruto <open@yceruto.dev>
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
final class MapInputValueResolver implements ValueResolverInterface
{
    public function __construct(
        private readonly ValueResolverInterface $builtinTypeResolver,
        private readonly ValueResolverInterface $backedEnumResolver,
        private readonly ValueResolverInterface $dateTimeResolver,
    ) {
    }

    public function resolve(string $argumentName, InputInterface $input, ReflectionMember $member): iterable
    {
        if (!$attribute = MapInput::tryFrom($member->getMember())) {
            return [];
        }

        return [$this->resolveMapInput($attribute, $input)];
    }

    private function resolveMapInput(MapInput $mapInput, InputInterface $input): object
    {
        $instance = $mapInput->getClass()->newInstanceWithoutConstructor();

        foreach ($mapInput->getDefinition() as $name => $spec) {
            // ignore required arguments that are not set yet (may happen in interactive mode)
            if ($spec instanceof Argument && $spec->isRequired() && \in_array($input->getArgument($spec->name), [null, []], true)) {
                continue;
            }

            $instance->$name = match (true) {
                $spec instanceof Argument => $this->resolveArgumentSpec($spec, $mapInput->getClass()->getProperty($name), $input),
                $spec instanceof Option => $this->resolveOptionSpec($spec, $mapInput->getClass()->getProperty($name), $input),
                $spec instanceof MapInput => $this->resolveMapInput($spec, $input),
            };
        }

        return $instance;
    }

    private function resolveArgumentSpec(Argument $argument, \ReflectionProperty $property, InputInterface $input): mixed
    {
        if (is_subclass_of($argument->typeName, \BackedEnum::class)) {
            return iterator_to_array($this->backedEnumResolver->resolve($property->name, $input, new ReflectionMember($property)))[0] ?? null;
        }

        if (is_a($argument->typeName, \DateTimeInterface::class, true)) {
            return iterator_to_array($this->dateTimeResolver->resolve($property->name, $input, new ReflectionMember($property)))[0] ?? null;
        }

        return iterator_to_array($this->builtinTypeResolver->resolve($property->name, $input, new ReflectionMember($property)))[0] ?? null;
    }

    private function resolveOptionSpec(Option $option, \ReflectionProperty $property, InputInterface $input): mixed
    {
        if (is_subclass_of($option->typeName, \BackedEnum::class)) {
            return iterator_to_array($this->backedEnumResolver->resolve($property->name, $input, new ReflectionMember($property)))[0] ?? null;
        }

        if (is_a($option->typeName, \DateTimeInterface::class, true)) {
            return iterator_to_array($this->dateTimeResolver->resolve($property->name, $input, new ReflectionMember($property)))[0] ?? null;
        }

        return iterator_to_array($this->builtinTypeResolver->resolve($property->name, $input, new ReflectionMember($property)))[0] ?? null;
    }
}
