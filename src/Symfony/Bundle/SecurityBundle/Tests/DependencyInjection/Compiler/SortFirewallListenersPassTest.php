<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Compiler\SortFirewallListenersPass;
use Symfony\Bundle\SecurityBundle\Security\FirewallContext;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Http\Firewall\FirewallListenerInterface;

class SortFirewallListenersPassTest extends TestCase
{
    public function testSortFirewallListeners()
    {
        $container = new ContainerBuilder();
        $container->setParameter('security.firewalls', ['main']);

        $container->register('listener_priority_minus1', FirewallListenerPriorityMinus1::class);
        $container->register('listener_priority_1', FirewallListenerPriority1::class);
        $container->register('listener_priority_2', FirewallListenerPriority2::class);
        $container->register('listener_interface_not_implemented', \stdClass::class);

        $firewallContext = $container->register('security.firewall.map.context.main', FirewallContext::class);
        $firewallContext->addTag('security.firewall_map_context');

        $listeners = new IteratorArgument([
            new Reference('listener_priority_minus1'),
            new Reference('listener_priority_1'),
            new Reference('listener_priority_2'),
            new Reference('listener_interface_not_implemented'),
        ]);

        $firewallContext->setArgument(0, $listeners);

        $compilerPass = new SortFirewallListenersPass();
        $compilerPass->process($container);

        $sortedListeners = $firewallContext->getArgument(0);
        $expectedSortedlisteners = [
            new Reference('listener_priority_2'),
            new Reference('listener_priority_1'),
            new Reference('listener_interface_not_implemented'),
            new Reference('listener_priority_minus1'),
        ];
        $this->assertEquals($expectedSortedlisteners, $sortedListeners->getValues());
    }
}

class FirewallListenerPriorityMinus1 implements FirewallListenerInterface
{
    public function supports(Request $request): ?bool
    {
    }

    public function authenticate(RequestEvent $event): void
    {
    }

    public static function getPriority(): int
    {
        return -1;
    }
}

class FirewallListenerPriority1 implements FirewallListenerInterface
{
    public function supports(Request $request): ?bool
    {
    }

    public function authenticate(RequestEvent $event): void
    {
    }

    public static function getPriority(): int
    {
        return 1;
    }
}

class FirewallListenerPriority2 implements FirewallListenerInterface
{
    public function supports(Request $request): ?bool
    {
    }

    public function authenticate(RequestEvent $event): void
    {
    }

    public static function getPriority(): int
    {
        return 2;
    }
}
