<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\ArgumentResolver;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * @author Robin Chalas <robin.chalas@gmail.com>
 */
class TraceableArgumentResolver implements ArgumentResolverInterface
{
    public function __construct(
        private ArgumentResolverInterface $resolver,
        private Stopwatch $stopwatch,
    ) {
    }

    public function getArguments(InputInterface $input, callable $command, ?\ReflectionFunctionAbstract $reflector = null): array
    {
        $e = $this->stopwatch->start('command.get_arguments');

        try {
            return $this->resolver->getArguments($input, $command, $reflector);
        } finally {
            $e->stop();
        }
    }
}
