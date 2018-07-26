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

use Symfony\Component\Routing\Matcher\Dumper\PhpMatcherDumper;
use Symfony\Component\Routing\Matcher\RedirectableUrlMatcherInterface;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

class DumpedRedirectableUrlMatcherTest extends RedirectableUrlMatcherTest
{
    protected function getUrlMatcher(RouteCollection $routes, RequestContext $context = null)
    {
        static $i = 0;

        $class = 'DumpedRedirectableUrlMatcher'.++$i;
        $dumper = new PhpMatcherDumper($routes);
        eval('?>'.$dumper->dump(array('class' => $class, 'base_class' => 'Symfony\Component\Routing\Tests\Matcher\TestDumpedRedirectableUrlMatcher')));

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
