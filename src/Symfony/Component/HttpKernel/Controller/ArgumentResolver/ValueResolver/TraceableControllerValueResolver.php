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

use Symfony\Component\ArgumentResolver\ArgumentMetadata\ArgumentMetadata;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ArgumentResolver\ValueResolver\ControllerValueResolverInterface;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * Provides timing information via the stopwatch.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 * @author Robin Chalas <robin@baksla.sh>
 */
final class TraceableControllerValueResolver implements ControllerValueResolverInterface
{
    public function __construct(
        private ControllerValueResolverInterface $inner,
        private Stopwatch $stopwatch,
    ) {
    }

    public function resolve(ArgumentMetadata $argument, ?Request $request = null): iterable
    {
        $method = $this->inner::class.'::'.__FUNCTION__;
        $this->stopwatch->start($method, 'argument_resolver.value_resolver');

        yield from $this->inner->resolve($argument, $request);

        $this->stopwatch->stop($method);
    }
}
