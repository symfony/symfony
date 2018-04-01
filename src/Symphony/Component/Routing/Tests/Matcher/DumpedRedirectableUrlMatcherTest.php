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
use Symphony\Component\Routing\Matcher\RedirectableUrlMatcherInterface;
use Symphony\Component\Routing\Matcher\UrlMatcher;
use Symphony\Component\Routing\RouteCollection;
use Symphony\Component\Routing\RequestContext;

class DumpedRedirectableUrlMatcherTest extends RedirectableUrlMatcherTest
{
    protected function getUrlMatcher(RouteCollection $routes, RequestContext $context = null)
    {
        static $i = 0;

        $class = 'DumpedRedirectableUrlMatcher'.++$i;
        $dumper = new PhpMatcherDumper($routes);
        eval('?>'.$dumper->dump(array('class' => $class, 'base_class' => 'Symphony\Component\Routing\Tests\Matcher\TestDumpedRedirectableUrlMatcher')));

        return $this->getMockBuilder($class)
            ->setConstructorArgs(array($context ?: new RequestContext()))
            ->setMethods(array('redirect'))
            ->getMock();
    }
}

class TestDumpedRedirectableUrlMatcher extends UrlMatcher implements RedirectableUrlMatcherInterface
{
    public function redirect($path, $route, $scheme = null)
    {
        return array();
    }
}
