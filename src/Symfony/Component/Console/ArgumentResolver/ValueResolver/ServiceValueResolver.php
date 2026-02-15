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

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\ArgumentResolver\Exception\NearMissValueResolverException;
use Symfony\Component\Console\Attribute\Reflection\ReflectionMember;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

/**
 * Yields a service from a service locator keyed by command and argument name.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
final class ServiceValueResolver implements ValueResolverInterface
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {
    }

    public function resolve(string $argumentName, InputInterface $input, ReflectionMember $member): iterable
    {
        $command = $input->getFirstArgument();

        if ($command && $this->container->has($command)) {
            $locator = $this->container->get($command);
            if ($locator instanceof ContainerInterface && $locator->has($argumentName)) {
                try {
                    return [$locator->get($argumentName)];
                } catch (RuntimeException|\Throwable $e) {
                    $what = \sprintf('argument $%s', $argumentName);
                    $message = str_replace(\sprintf('service "%s"', $argumentName), $what, $e->getMessage());
                    $what .= \sprintf(' of command "%s"', $command);
                    $message = preg_replace('/service "\.service_locator\.[^"]++"/', $what, $message);

                    if ($e->getMessage() === $message) {
                        $message = \sprintf('Cannot resolve %s: %s', $what, $message);
                    }

                    throw new NearMissValueResolverException($message, $e->getCode(), $e);
                }
            }
        }

        $type = $member->getType();

        if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
            return [];
        }

        $typeName = $type->getName();

        if (!$this->container->has($typeName)) {
            return [];
        }

        try {
            $service = $this->container->get($typeName);

            if (!$service instanceof $typeName) {
                throw new NearMissValueResolverException(\sprintf('Service "%s" exists in the container but is not an instance of "%s".', $typeName, $typeName));
            }

            return [$service];
        } catch (\Throwable $e) {
            throw new NearMissValueResolverException(\sprintf('Cannot resolve parameter "$%s" of type "%s": %s', $argumentName, $typeName, $e->getMessage()), previous: $e);
        }
    }
}
