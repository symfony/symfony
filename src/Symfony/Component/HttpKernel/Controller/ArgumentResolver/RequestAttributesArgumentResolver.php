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
 * Resolves arguments which names are equal to the name of a request attribute.
 *
 * @author Wouter J <wouter@wouterj.nl>
 */
class RequestAttributesArgumentResolver implements ArgumentResolverInterface
{
    /**
     * {@inheritdoc}
     */
    public function supports(Request $request, \ReflectionParameter $parameter)
    {
        return $request->attributes->has($parameter->name);
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(Request $request, \ReflectionParameter $parameter)
    {
        return $request->attributes->get($parameter->name);
    }
}
