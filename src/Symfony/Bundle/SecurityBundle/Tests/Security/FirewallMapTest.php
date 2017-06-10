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

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security\FirewallContext;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;

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
        $firewallContext->expects($this->once())->method('getConfig')->willReturn('CONFIG');
        $firewallContext->expects($this->once())->method('getContext')->willReturn(array('LISTENERS', 'EXCEPTION LISTENER'));

        $matcher = $this->getMockBuilder(RequestMatcherInterface::class)->getMock();
        $matcher->expects($this->once())
            ->method('matches')
            ->with($request)
            ->willReturn(true);

        $container = $this->getMockBuilder(Container::class)->getMock();
        $container->expects($this->exactly(2))->method('get')->willReturn($firewallContext);

        $firewallMap = new FirewallMap($container, array('security.firewall.map.context.foo' => $matcher));

        $this->assertEquals(array('LISTENERS', 'EXCEPTION LISTENER'), $firewallMap->getListeners($request));
        $this->assertEquals('CONFIG', $firewallMap->getFirewallConfig($request));
        $this->assertEquals('security.firewall.map.context.foo', $request->attributes->get(self::ATTRIBUTE_FIREWALL_CONTEXT));
    }
}
