<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ArgumentResolver;

use Psr\Container\ContainerInterface;
use Symfony\Component\ArgumentResolver\ArgumentMetadata\ArgumentMetadata;
use Symfony\Component\ArgumentResolver\ArgumentMetadata\ArgumentMetadataFactory;
use Symfony\Component\ArgumentResolver\ArgumentMetadata\ArgumentMetadataFactoryInterface;
use Symfony\Component\ArgumentResolver\Exception\InvalidArgumentException;
use Symfony\Component\ArgumentResolver\Exception\LogicException;
use Symfony\Component\ArgumentResolver\Exception\NearMissValueResolverException;
use Symfony\Component\ArgumentResolver\Exception\RuntimeException;
use Symfony\Component\ArgumentResolver\ValueResolver\DefaultValueResolver;
use Symfony\Component\ArgumentResolver\Attribute\ValueResolver;
use Symfony\Component\ArgumentResolver\Exception\ResolverNotFoundException;
use Symfony\Contracts\Service\ServiceProviderInterface;
use Symfony\Component\ArgumentResolver\ValueResolver\ValueResolverInterface;

/**
 * Responsible for resolving the arguments passed to an action.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 * @author Robin Chalas <robin@baksla.sh>
 */
class ArgumentResolver implements ArgumentResolverInterface
{
    private readonly ArgumentMetadataFactoryInterface $argumentMetadataFactory;

    /**
     * @param iterable<mixed, ValueResolverInterface> $argumentValueResolvers
     */
    public function __construct(
        ?ArgumentMetadataFactoryInterface $argumentMetadataFactory = null,
        private iterable $argumentValueResolvers = [],
        private readonly ?ContainerInterface $namedResolvers = null,
    ) {
        $this->argumentMetadataFactory = $argumentMetadataFactory ?? new ArgumentMetadataFactory();
        $this->argumentValueResolvers = $argumentValueResolvers ?: static::getDefaultValueResolvers();
    }

    public function getArguments(mixed $input, callable $callable, ?\ReflectionFunctionAbstract $reflector = null): array
    {
        $arguments = [];

        foreach ($this->argumentMetadataFactory->createArgumentMetadata($callable, $reflector) as $metadata) {
            $argumentValueResolvers = $this->argumentValueResolvers;
            $disabledResolvers = [];

            if ($this->namedResolvers && $attributes = $metadata->getAttributesOfType(ValueResolver::class, $metadata::IS_INSTANCEOF)) {
                $resolverName = null;
                foreach ($attributes as $attribute) {
                    if ($attribute->disabled) {
                        $disabledResolvers[$attribute->resolver] = true;
                    } elseif ($resolverName) {
                        throw new LogicException(\sprintf('You can only pin one resolver per argument, but argument "$%s" of "%s()" has more.', $metadata->getName(), $metadata->getCallableName()));
                    } else {
                        $resolverName = $attribute->resolver;
                    }
                }

                if ($resolverName) {
                    if (!$this->namedResolvers->has($resolverName)) {
                        throw new ResolverNotFoundException($resolverName, $this->namedResolvers instanceof ServiceProviderInterface ? array_keys($this->namedResolvers->getProvidedServices()) : []);
                    }

                    $argumentValueResolvers = [$this->namedResolvers->get($resolverName), ...$this->getExtraValueResolversForNamed()];
                }
            }

            $valueResolverExceptions = [];
            foreach ($argumentValueResolvers as $name => $resolver) {
                if (isset($disabledResolvers[\is_int($name) ? $resolver::class : $name])) {
                    continue;
                }

                try {
                    $count = 0;
                    foreach ($this->callResolver($resolver, $metadata, $input) as $argument) {
                        ++$count;
                        $arguments[] = $argument;
                    }
                } catch (NearMissValueResolverException $e) {
                    $valueResolverExceptions[] = $e;
                }

                if (1 < $count && !$metadata->isVariadic()) {
                    throw new InvalidArgumentException(\sprintf('"%s::resolve()" must yield at most one value for non-variadic arguments.', get_debug_type($resolver)));
                }

                if ($count) {
                    // continue to the next controller argument
                    continue 2;
                }
            }

            $reasons = array_map(static fn (NearMissValueResolverException $e) => $e->getMessage(), $valueResolverExceptions);
            if (!$reasons) {
                $reasons[] = 'Either the argument is nullable and no null value has been provided, no default value has been provided or there is a non-optional argument after this one.';
            }

            $reasonCounter = 1;
            if (\count($reasons) > 1) {
                foreach ($reasons as $i => $reason) {
                    $reasons[$i] = $reasonCounter.') '.$reason;
                    ++$reasonCounter;
                }
            }

            throw new RuntimeException(\sprintf('Controller "%s" requires the "$%s" argument that could not be resolved. '.($reasonCounter > 1 ? 'Possible reasons: ' : '').'%s', $metadata->getCallableName(), $metadata->getName(), implode(' ', $reasons)));
        }

        return $arguments;
    }

    protected static function getDefaultValueResolvers(): iterable
    {
        return [
            new DefaultValueResolver(),
        ];
    }

    protected static function getExtraValueResolversForNamed(): array
    {
        return [
            new DefaultValueResolver(),
        ];
    }

    /**
     * @param ValueResolverInterface $resolver
     */
    protected function callResolver($resolver, ArgumentMetadata $metadata, mixed $input): iterable
    {
        return $resolver->resolve($metadata, $input);
    }
}
