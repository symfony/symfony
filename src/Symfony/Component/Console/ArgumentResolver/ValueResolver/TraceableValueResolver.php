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

use Symfony\Component\Console\Attribute\Reflection\ReflectionMember;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
final class TraceableValueResolver implements ValueResolverInterface
{
    public function __construct(
        private ValueResolverInterface $inner,
        private Stopwatch $stopwatch,
    ) {
    }

    public function resolve(string $argumentName, InputInterface $input, ReflectionMember $member): iterable
    {
        $method = $this->inner::class.'::'.__FUNCTION__;
        $this->stopwatch->start($method, 'command.argument_value_resolver');

        yield from $this->inner->resolve($argumentName, $input, $member);

        $this->stopwatch->stop($method);
    }
}
