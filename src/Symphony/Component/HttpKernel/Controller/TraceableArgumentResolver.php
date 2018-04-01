<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\HttpKernel\Controller;

use Symphony\Component\Stopwatch\Stopwatch;
use Symphony\Component\HttpFoundation\Request;

/**
 * @author Fabien Potencier <fabien@symphony.com>
 */
class TraceableArgumentResolver implements ArgumentResolverInterface
{
    private $resolver;
    private $stopwatch;

    public function __construct(ArgumentResolverInterface $resolver, Stopwatch $stopwatch)
    {
        $this->resolver = $resolver;
        $this->stopwatch = $stopwatch;
    }

    /**
     * {@inheritdoc}
     */
    public function getArguments(Request $request, $controller)
    {
        $e = $this->stopwatch->start('controller.get_arguments');

        $ret = $this->resolver->getArguments($request, $controller);

        $e->stop();

        return $ret;
    }
}
