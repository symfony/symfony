<?php

namespace Symfony\Component\HttpKernel\Security;

use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * AccessMap allows configuration of different access control rules for
 * specific parts of the website.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class AccessMap
{
    protected $map = array();

    /**
     * Constructor.
     *
     * @param RequestMatcherInterface $requestMatcher A RequestMatcherInterface instance
     * @param array                   $roles          An array of roles needed to access the resource
     * @param string|null             $channel        The channel to enforce (http, https, or null)
     */
    public function add(RequestMatcherInterface $requestMatcher, array $roles = array(), $channel = null)
    {
        $this->map[] = array($requestMatcher, $roles, $channel);
    }

    public function getPatterns(Request $request)
    {
        foreach ($this->map as $elements) {
            if (null === $elements[0] || $elements[0]->matches($request)) {
                return array($elements[1], $elements[2]);
            }
        }

        return array(null, null);
    }
}
