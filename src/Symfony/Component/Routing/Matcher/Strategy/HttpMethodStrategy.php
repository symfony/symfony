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

class HttpMethodStrategy implements MatcherStrategy
{
    /**
     * @param string $pathinfo
     * @param Route $route
     * @param RequestContext $context
     * @return bool
     */
    public function matches($pathinfo, Route $route, RequestContext $context)
    {
        if ($req = $route->getRequirement('_method')) {
            // HEAD and GET are equivalent as per RFC
            $method = $context->getMethod();
            if ('HEAD' === $method) {
                $method = 'GET';
            }

            if (!in_array($method, $req = explode('|', strtoupper($req)))) {
                return false;
            }
        }

        return true;
    }
}
