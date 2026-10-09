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
use Symfony\Component\DependencyInjection\Compiler\DecoratorServicePass;
use Symfony\Component\DependencyInjection\Compiler\ResolveReferencesToAliasesPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Http\Attribute\FirewallListenerOrder;
use Symfony\Component\Security\Http\Firewall\AbstractListener;
use Symfony\Component\Security\Http\Firewall\AccessListener;
use Symfony\Component\Security\Http\Firewall\FirewallListenerInterface;
use Symfony\Component\Security\Http\Firewall\LogoutListener;

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

    public function testConstraintsReorderListenersSharingAPriority()
    {
        $container = $this->createContainer(['a' => ListenerA::class, 'b' => ListenerBeforeA::class]);

        (new SortFirewallListenersPass())->process($container);

        $this->assertEquals([new Reference('b'), new Reference('a')], $this->getListeners($container));
    }

    public function testConstraintCrossingAPriorityThrows()
    {
        $container = $this->createContainer(['p2' => FirewallListenerPriority2::class, 'p1' => ListenerPriority1BeforePriority2::class]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot order the listeners of firewall "main": the priority of "p1" (1) contradicts its "before" constraint on "p2" (2): raise it to 2 or more, remove it, or drop the constraint.');

        (new SortFirewallListenersPass())->process($container);
    }

    public function testConstraintOnAbsentTargetIsIgnored()
    {
        $container = $this->createContainer(['a' => ListenerA::class, 'c' => ListenerBetweenLogoutAndAccess::class]);

        (new SortFirewallListenersPass())->process($container);

        $this->assertEquals([new Reference('a'), new Reference('c')], $this->getListeners($container));
    }

    public function testConstraintsSeeThroughDecorators()
    {
        $container = $this->createContainer(['a' => ListenerA::class, 'b' => ListenerBeforeA::class, 'c' => ListenerBeforeServiceA::class]);
        $container->register('debug.a', ListenerC::class)->setDecoratedService('a');
        $container->register('debug.b', ListenerC::class)->setDecoratedService('b');

        (new DecoratorServicePass())->process($container);
        (new ResolveReferencesToAliasesPass())->process($container);
        (new SortFirewallListenersPass())->process($container);

        $this->assertEquals([new Reference('debug.b'), new Reference('c'), new Reference('debug.a')], $this->getListeners($container));
    }

    public function testConstraintsOnTheLogoutListener()
    {
        $container = $this->createContainer(['access' => AccessListener::class, 'c' => ListenerBetweenLogoutAndAccess::class, 'a' => ListenerA::class], 'logout');

        (new SortFirewallListenersPass())->process($container);

        $this->assertEquals([new Reference('a'), new Reference('c'), new Reference('access')], $this->getListeners($container));
    }

    public function testConstraintAfterTheLogoutListenerAtItsPriorityThrows()
    {
        $container = $this->createContainer(['l' => ListenerAtLogoutPriorityAfterLogout::class], 'logout');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot order the listeners of firewall "main": "l" cannot run after "logout", because the firewall calls the logout listener after every listener of its priority (-127); lower the priority of "l" or drop the constraint.');

        (new SortFirewallListenersPass())->process($container);
    }

    public function testConstraintAfterTheLogoutListenerAtAHigherPriorityThrows()
    {
        $container = $this->createContainer(['a' => ListenerAfterLogout::class], 'logout');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot order the listeners of firewall "main": the priority of "a" (0) contradicts its "after" constraint on "logout" (-127): lower it to -127 or less, remove it, or drop the constraint.');

        (new SortFirewallListenersPass())->process($container);
    }

    private function createContainer(array $listeners, ?string $logoutListener = null): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('security.firewalls', ['main']);

        foreach ($listeners as $id => $class) {
            $container->register($id, $class);
        }

        if ($logoutListener) {
            $container->register($logoutListener, LogoutListener::class);
        }

        $container->register('security.firewall.map.context.main', FirewallContext::class)
            ->setArguments([new IteratorArgument(array_map(static fn ($id) => new Reference($id), array_keys($listeners))), null, $logoutListener ? new Reference($logoutListener) : null]);

        return $container;
    }

    private function getListeners(ContainerBuilder $container): array
    {
        return $container->getDefinition('security.firewall.map.context.main')->getArgument(0)->getValues();
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

abstract class TestListener extends AbstractListener
{
    public function supports(Request $request): ?bool
    {
        return true;
    }

    public function authenticate(RequestEvent $event): void
    {
    }
}

class ListenerA extends TestListener
{
}

class ListenerC extends TestListener
{
}

#[FirewallListenerOrder(before: ListenerA::class)]
class ListenerBeforeA extends TestListener
{
}

#[FirewallListenerOrder(before: 'a')]
class ListenerBeforeServiceA extends TestListener
{
}

#[FirewallListenerOrder(before: FirewallListenerPriority2::class)]
class ListenerPriority1BeforePriority2 extends TestListener
{
    public static function getPriority(): int
    {
        return 1;
    }
}

#[FirewallListenerOrder(before: AccessListener::class, after: LogoutListener::class)]
class ListenerBetweenLogoutAndAccess extends TestListener
{
    public static function getPriority(): int
    {
        return -191;
    }
}

#[FirewallListenerOrder(after: LogoutListener::class)]
class ListenerAfterLogout extends TestListener
{
}

#[FirewallListenerOrder(after: LogoutListener::class)]
class ListenerAtLogoutPriorityAfterLogout extends TestListener
{
    public static function getPriority(): int
    {
        return -127;
    }
}
