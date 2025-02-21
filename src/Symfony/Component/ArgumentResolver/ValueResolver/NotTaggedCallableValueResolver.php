<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ArgumentResolver\ValueResolver;

use Psr\Container\ContainerInterface;
use Symfony\Component\ArgumentResolver\ArgumentMetadata\ArgumentMetadata;
use Symfony\Component\ArgumentResolver\SourceValue;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

/**
 * Provides an intuitive error message when controller fails because it is not registered as a service.
 *
 * @author Simeon Kolev <simeon.kolev9@gmail.com>
 */
final readonly class NotTaggedCallableValueResolver implements ValueResolverInterface
{
    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    public function resolveArgument(ArgumentMetadata $argument, SourceValue $value): iterable
    {
        $callable = $value->get();

        if (\is_array($callable) && \is_callable($callable, true) && \is_string($callable[0])) {
            $callable = $callable[0].'::'.$callable[1];
        } elseif (!\is_string($callable) || '' === $callable || SourceValue::NOT_FOUND === $callable) {
            return [];
        }

        if ('\\' === $callable[0]) {
            $callable = ltrim($callable, '\\');
        }

        if (!$this->container->has($callable)) {
            $callable = (false !== $i = strrpos($callable, ':'))
                ? substr($callable, 0, $i).strtolower(substr($callable, $i))
                : $callable.'::__invoke';
        }

        if ($this->container->has($callable)) {
            return [];
        }

        throw new RuntimeException(\sprintf('Could not resolve argument $%s of "%s()", maybe you forgot to register it as a service?', $argument->getName(), $callable));
    }
}
