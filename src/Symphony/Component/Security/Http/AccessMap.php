<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Security\Http;

use Symphony\Component\HttpFoundation\RequestMatcherInterface;
use Symphony\Component\HttpFoundation\Request;

/**
 * AccessMap allows configuration of different access control rules for
 * specific parts of the website.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
class AccessMap implements AccessMapInterface
{
    private $map = array();

    /**
     * @param RequestMatcherInterface $requestMatcher A RequestMatcherInterface instance
     * @param array                   $attributes     An array of attributes to pass to the access decision manager (like roles)
     * @param string|null             $channel        The channel to enforce (http, https, or null)
     */
    public function add(RequestMatcherInterface $requestMatcher, array $attributes = array(), $channel = null)
    {
        $this->map[] = array($requestMatcher, $attributes, $channel);
    }

    /**
     * {@inheritdoc}
     */
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
