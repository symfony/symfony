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
use Symfony\Bundle\SecurityBundle\Security\FirewallConfig;
use Symfony\Bundle\SecurityBundle\Security\FirewallContext;
use Symfony\Bundle\SecurityBundle\Security\FirewallMap;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\Security\Http\Firewall\ExceptionListener;
use Symfony\Component\Security\Http\Firewall\LogoutListener;

class FirewallMapTest extends TestCase
{
    private const ATTRIBUTE_FIREWALL_CONTEXT = '_firewall_context';

    public function testGetListenersWithEmptyMap()
    {
        $request = new Request();

        $map = [];
        $container = self::createMock(Container::class);
        $container->expects(self::never())->method('get');

        $firewallMap = new FirewallMap($container, $map);

        self::assertEquals([[], null, null], $firewallMap->getListeners($request));
        self::assertNull($firewallMap->getFirewallConfig($request));
        self::assertFalse($request->attributes->has(self::ATTRIBUTE_FIREWALL_CONTEXT));
    }

    public function testGetListenersWithInvalidParameter()
    {
        $request = new Request();
        $request->attributes->set(self::ATTRIBUTE_FIREWALL_CONTEXT, 'foo');

        $map = [];
        $container = self::createMock(Container::class);
        $container->expects(self::never())->method('get');

        $firewallMap = new FirewallMap($container, $map);

        self::assertEquals([[], null, null], $firewallMap->getListeners($request));
        self::assertNull($firewallMap->getFirewallConfig($request));
        self::assertFalse($request->attributes->has(self::ATTRIBUTE_FIREWALL_CONTEXT));
    }

    public function testGetListeners()
    {
        $request = new Request();

        $firewallContext = self::createMock(FirewallContext::class);

        $firewallConfig = new FirewallConfig('main', 'user_checker');
        $firewallContext->expects(self::once())->method('getConfig')->willReturn($firewallConfig);

        $listener = function () {};
        $firewallContext->expects(self::once())->method('getListeners')->willReturn([$listener]);

        $exceptionListener = self::createMock(ExceptionListener::class);
        $firewallContext->expects(self::once())->method('getExceptionListener')->willReturn($exceptionListener);

        $logoutListener = self::createMock(LogoutListener::class);
        $firewallContext->expects(self::once())->method('getLogoutListener')->willReturn($logoutListener);

        $matcher = self::createMock(RequestMatcherInterface::class);
        $matcher->expects(self::once())
            ->method('matches')
            ->with($request)
            ->willReturn(true);

        $container = self::createMock(Container::class);
        $container->expects(self::exactly(2))->method('get')->willReturn($firewallContext);

        $firewallMap = new FirewallMap($container, ['security.firewall.map.context.foo' => $matcher]);

        self::assertEquals([[$listener], $exceptionListener, $logoutListener], $firewallMap->getListeners($request));
        self::assertEquals($firewallConfig, $firewallMap->getFirewallConfig($request));
        self::assertEquals('security.firewall.map.context.foo', $request->attributes->get(self::ATTRIBUTE_FIREWALL_CONTEXT));
    }
}
