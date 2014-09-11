<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Matcher\Strategy;

use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;

class RegexStrategy implements MatcherStrategy
{
    /**
     * @param string $pathinfo
     * @param Route $route
     * @param RequestContext $context
     * @return bool
     */
    public function matches($pathinfo, Route $route, RequestContext $context)
    {
        return preg_match($route->compile()->getRegex(), $pathinfo);
    }
}
