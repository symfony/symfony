<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TraceableArgumentResolver implements ArgumentResolverInterface
{
    private ArgumentResolverInterface $resolver;
    private Stopwatch $stopwatch;

    public function __construct(ArgumentResolverInterface $resolver, Stopwatch $stopwatch)
    {
        $this->resolver = $resolver;
        $this->stopwatch = $stopwatch;
    }

    /**
     * @param \ReflectionFunctionAbstract|null $reflector
     */
    public function getArguments(Request $request, callable $controller/* , \ReflectionFunctionAbstract $reflector = null */): array
    {
        $reflector = 2 < \func_num_args() ? func_get_arg(2) : null;
        $e = $this->stopwatch->start('controller.get_arguments');

        $ret = $this->resolver->getArguments($request, $controller, $reflector);

        $e->stop();

        return $ret;
    }
}
