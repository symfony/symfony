<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Routing\Tests\Fixtures;

use Symphony\Component\Routing\Matcher\UrlMatcher;
use Symphony\Component\Routing\Matcher\RedirectableUrlMatcherInterface;

/**
 * @author Fabien Potencier <fabien@symphony.com>
 */
class RedirectableUrlMatcher extends UrlMatcher implements RedirectableUrlMatcherInterface
{
    public function redirect($path, $route, $scheme = null)
    {
        return array(
            '_controller' => 'Some controller reference...',
            'path' => $path,
            'scheme' => $scheme,
        );
    }
}
