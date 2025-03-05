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
use Symfony\Component\ArgumentResolver\ArgumentValueSource\SourceValue;
use Symfony\Component\ArgumentResolver\Exception\NearMissValueResolverException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

/**
 * Yields a service keyed by callable name and argument name.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Robin Chalas <robin@baksla.sh>
 */
final readonly class ServiceValueResolver implements ValueResolverInterface
{
    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    public function resolveArgument(ArgumentMetadata $argument, SourceValue $value): iterable
    {
        $callable = $value->get();

        if (SourceValue::NOT_FOUND === $callable) {
            return [];
        }

        if (\is_array($callable) && \is_callable($callable, true) && \is_string($callable[0])) {
            $callable = $callable[0].'::'.$callable[1];
        } elseif (!\is_string($callable) || '' === $callable) {
            return [];
        }

        if ('\\' === $callable[0]) {
            $callable = ltrim($callable, '\\');
        }

        if (!$this->container->has($callable) && false !== $i = strrpos($callable, ':')) {
            $callable = substr($callable, 0, $i).strtolower(substr($callable, $i));
        }

        if (!$this->container->has($callable) || !$this->container->get($callable)->has($argument->getName())) {
            return [];
        }

        try {
            return [$this->container->get($callable)->get($argument->getName())];
        } catch (RuntimeException $e) {
            $what = 'argument $'.$argument->getName();
            $message = str_replace(\sprintf('service "%s"', $argument->getName()), $what, $e->getMessage());
            $what .= \sprintf(' of "%s()"', $callable);
            $message = preg_replace('/service "\.service_locator\.[^"]++"/', $what, $message);

            if ($e->getMessage() === $message) {
                $message = \sprintf('Cannot resolve %s: %s', $what, $message);
            }

            throw new NearMissValueResolverException($message, $e->getCode(), $e);
        }
    }
}
