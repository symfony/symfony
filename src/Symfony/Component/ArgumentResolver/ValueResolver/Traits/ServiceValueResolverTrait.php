<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ArgumentResolver\ValueResolver\Traits;

use Psr\Container\ContainerInterface;
use Symfony\Component\ArgumentResolver\ArgumentMetadata\ArgumentMetadata;
use Symfony\Component\ArgumentResolver\Exception\NearMissValueResolverException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;

/**
 * Yields a service keyed by callable name and argument name.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 * @author Robin Chalas <robin@baksla.sh>
 */
trait ServiceValueResolverTrait
{
    public function __construct(
        private ContainerInterface $container,
    ) {
    }

    private function doResolve(ArgumentMetadata $argument, array $rawValues): iterable
    {
        $callable = $rawValues[0];

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
