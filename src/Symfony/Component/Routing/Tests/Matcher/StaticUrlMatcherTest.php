<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\Matcher;

use Symfony\Component\Routing\Matcher\Dumper\StaticUrlMatcherDumper;
use Symfony\Component\Routing\Matcher\StaticUrlMatcher;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RequestContext;

class StaticUrlMatcherTest extends UrlMatcherTest
{
    protected function getUrlMatcher(RouteCollection $routes, RequestContext $context)
    {
        $dumper = new StaticUrlMatcherDumper($routes);
        $dumpedRoutes = eval('?>'.$dumper->dump());

        return new StaticUrlMatcher($dumpedRoutes, $context);
    }
}
