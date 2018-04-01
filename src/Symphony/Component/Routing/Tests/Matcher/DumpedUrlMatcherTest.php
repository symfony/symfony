<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Routing\Tests\Matcher;

use Symphony\Component\Routing\Matcher\Dumper\PhpMatcherDumper;
use Symphony\Component\Routing\RouteCollection;
use Symphony\Component\Routing\RequestContext;

class DumpedUrlMatcherTest extends UrlMatcherTest
{
    protected function getUrlMatcher(RouteCollection $routes, RequestContext $context = null)
    {
        static $i = 0;

        $class = 'DumpedUrlMatcher'.++$i;
        $dumper = new PhpMatcherDumper($routes);
        eval('?>'.$dumper->dump(array('class' => $class)));

        return new $class($context ?: new RequestContext());
    }
}
