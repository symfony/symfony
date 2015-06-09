<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Controller\ArgumentResolver;

use Symfony\Component\HttpFoundation\Request;

/**
 * Resolves arguments typehinting for the HttpFoundation Request object.
 *
 * @author Wouter J <wouter@wouterj.nl>
 */
class RequestArgumentResolver implements ArgumentResolverInterface
{
    /**
     * {@inheritdoc}
     */
    public function supports(Request $request, \ReflectionParameter $parameter)
    {
        $class = $parameter->getClass();

        return $class && $class->isInstance($request);
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(Request $request, \ReflectionParameter $parameter)
    {
        return $request;
    }
}
