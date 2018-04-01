<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\SecurityBundle\Tests\Security;

use PHPUnit\Framework\TestCase;
use Symphony\Bundle\SecurityBundle\Security\FirewallConfig;
use Symphony\Bundle\SecurityBundle\Security\FirewallContext;
use Symphony\Bundle\SecurityBundle\Security\FirewallMap;
use Symphony\Component\DependencyInjection\Container;
use Symphony\Component\HttpFoundation\Request;
use Symphony\Component\HttpFoundation\RequestMatcherInterface;
use Symphony\Component\Security\Http\Firewall\ExceptionListener;
use Symphony\Component\Security\Http\Firewall\ListenerInterface;

class FirewallMapTest extends TestCase
{
    const ATTRIBUTE_FIREWALL_CONTEXT = '_firewall_context';

    public function testGetListenersWithEmptyMap()
    {
        $request = new Request();

        $map = array();
        $container = $this->getMockBuilder(Container::class)->getMock();
        $container->expects($this->never())->method('get');

        $firewallMap = new FirewallMap($container, $map);

        $this->assertEquals(array(array(), null), $firewallMap->getListeners($request));
        $this->assertNull($firewallMap->getFirewallConfig($request));
        $this->assertFalse($request->attributes->has(self::ATTRIBUTE_FIREWALL_CONTEXT));
    }

    public function testGetListenersWithInvalidParameter()
    {
        $request = new Request();
        $request->attributes->set(self::ATTRIBUTE_FIREWALL_CONTEXT, 'foo');

        $map = array();
        $container = $this->getMockBuilder(Container::class)->getMock();
        $container->expects($this->never())->method('get');

        $firewallMap = new FirewallMap($container, $map);

        $this->assertEquals(array(array(), null), $firewallMap->getListeners($request));
        $this->assertNull($firewallMap->getFirewallConfig($request));
        $this->assertFalse($request->attributes->has(self::ATTRIBUTE_FIREWALL_CONTEXT));
    }

    public function testGetListeners()
    {
        $request = new Request();

        $firewallContext = $this->getMockBuilder(FirewallContext::class)->disableOriginalConstructor()->getMock();

        $firewallConfig = new FirewallConfig('main', 'user_checker');
        $firewallContext->expects($this->once())->method('getConfig')->willReturn($firewallConfig);

        $listener = $this->getMockBuilder(ListenerInterface::class)->getMock();
        $firewallContext->expects($this->once())->method('getListeners')->willReturn(array($listener));

        $exceptionListener = $this->getMockBuilder(ExceptionListener::class)->disableOriginalConstructor()->getMock();
        $firewallContext->expects($this->once())->method('getExceptionListener')->willReturn($exceptionListener);

        $matcher = $this->getMockBuilder(RequestMatcherInterface::class)->getMock();
        $matcher->expects($this->once())
            ->method('matches')
            ->with($request)
            ->willReturn(true);

        $container = $this->getMockBuilder(Container::class)->getMock();
        $container->expects($this->exactly(2))->method('get')->willReturn($firewallContext);

        $firewallMap = new FirewallMap($container, array('security.firewall.map.context.foo' => $matcher));

        $this->assertEquals(array(array($listener), $exceptionListener), $firewallMap->getListeners($request));
        $this->assertEquals($firewallConfig, $firewallMap->getFirewallConfig($request));
        $this->assertEquals('security.firewall.map.context.foo', $request->attributes->get(self::ATTRIBUTE_FIREWALL_CONTEXT));
    }
}
