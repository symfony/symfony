<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Security;

use Symfony\Bundle\SecurityBundle\Security\FirewallConfig;
use Symfony\Bundle\SecurityBundle\Security\FirewallConfigRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher;
use Symfony\Component\HttpFoundation\RequestStack;

class FirewallConfigRegistryTest extends \PHPUnit_Framework_TestCase
{
    public function testGet()
    {
        $registry = new FirewallConfigRegistry(array(
            $adminConfig = new FirewallConfig('admin', 'admin_request_matcher', 'user_checker'),
            $appConfig = new FirewallConfig('app', '', 'user_checker'),
        ));

        $this->assertSame($adminConfig, $registry->get('admin'));
        $this->assertSame($appConfig, $registry->get('app'));
    }

    public function testAll()
    {
        $registry = new FirewallConfigRegistry(array(
            $adminConfig = new FirewallConfig('admin', 'admin_request_matcher', 'user_checker'),
            $appConfig = new FirewallConfig('app', '', 'user_checker'),
        ));

        $this->assertSame(array($adminConfig, $appConfig), $registry->all());
    }

    public function testInContext()
    {
        $registry = new FirewallConfigRegistry(array(
            $adminConfig = new FirewallConfig('admin', 'admin_request_matcher', 'user_checker', true, false, null, 'secured'),
            $appConfig = new FirewallConfig('app', '', 'user_checker', true, false, null, 'secured'),
            new FirewallConfig('stateless', '', 'user_checker', true, true, null, null),
        ));

        $this->assertSame(array($adminConfig, $appConfig), $registry->inContext('secured'));
    }

    public function testFromRequest()
    {
        $registry = new FirewallConfigRegistry(array(
            $adminConfig = new FirewallConfig('admin', 'admin_request_matcher', 'user_checker', true, false, null, 'secured'),
            $appConfig = new FirewallConfig('app', '', 'user_checker', true, false, null, 'secured'),
        ), array(
            'admin' => new RequestMatcher('/admin'),
        ));

        $request = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $request->method('getPathInfo')->willReturn('/admin');

        $this->assertSame($adminConfig, $registry->fromRequest($request));
    }

    public function testCurrent()
    {
        $requestStack = new RequestStack();

        $request = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock()
        ;
        $request->method('getPathInfo')->willReturn('/admin');
        $requestStack->push($request);

        $registry = new FirewallConfigRegistry(array(
            $adminConfig = new FirewallConfig('admin', 'admin_request_matcher', 'user_checker', true, false, null, 'secured'),
            $appConfig = new FirewallConfig('app', '', 'user_checker', true, false, null, 'secured'),
        ), array(
            'admin' => new RequestMatcher('/admin'),
        ), $requestStack);

        $this->assertSame($adminConfig, $registry->current());
    }
}
