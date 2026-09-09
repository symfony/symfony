<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\DefaultMessageBusPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;

class DefaultMessageBusPassTest extends TestCase
{
    public function testTheWorkerRestartPoolIsRemovedWhenNoBusIsRegistered()
    {
        $container = $this->createContainer(false);

        new DefaultMessageBusPass()->process($container);

        $this->assertFalse($container->hasDefinition('cache.messenger.restart_workers_signal'));
    }

    public function testTheSchedulerThrowsWhenNoBusIsRegistered()
    {
        $container = $this->createContainer(false);
        $container->register('scheduler.event_listener', \stdClass::class);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Scheduler support cannot be enabled as the Messenger component is not enabled.');

        new DefaultMessageBusPass()->process($container);
    }

    public function testTheNotifierChannelsKeepTheirTransportWhenNoBusIsRegistered()
    {
        $container = $this->createContainer(false);
        $container->setParameter('.notifier.notification_on_failed_messages', true);

        new DefaultMessageBusPass()->process($container);

        $this->assertSame('chatter.transports', (string) $container->getDefinition('notifier.channel.chat')->getArgument(0));
        $this->assertFalse($container->getDefinition('notifier.failed_message_listener')->hasTag('kernel.event_subscriber'));
    }

    public function testTheClaimCheckPoolNeedsADefaultLifetime()
    {
        $container = $this->createContainer(true);
        $container->register('app.claim_check_pool', \stdClass::class)->addTag('cache.pool', ['name' => 'app.claim_check_pool']);
        $container->register('.messenger.transport.async.claim_check_serializer', \stdClass::class)
            ->setArguments([new Reference('messenger.default_serializer'), new Reference('app.claim_check_pool'), 200000]);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The cache pool "app.claim_check_pool" used by Messenger transport "async" for claim checks must define a "default_lifetime".');

        new DefaultMessageBusPass()->process($container);
    }

    public function testTheClaimCheckPoolPassesWithADefaultLifetime()
    {
        $container = $this->createContainer(true);
        $container->register('app.claim_check_pool', \stdClass::class)->addTag('cache.pool', ['default_lifetime' => 604800]);
        $container->register('.messenger.transport.async.claim_check_serializer', \stdClass::class)
            ->setArguments([new Reference('messenger.default_serializer'), new Reference('app.claim_check_pool'), 200000]);

        new DefaultMessageBusPass()->process($container);

        $this->assertTrue($container->hasDefinition('.messenger.transport.async.claim_check_serializer'));
    }

    private function createContainer(bool $withDefaultBus): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register('cache.messenger.restart_workers_signal', \stdClass::class);
        $container->register('notifier', \stdClass::class);
        $container->register('notifier.failed_message_listener', \stdClass::class);
        $container->register('notifier.channel.chat', \stdClass::class)->setArguments([new Reference('chatter.transports'), null]);
        $container->register('notifier.channel.sms', \stdClass::class)->setArguments([new Reference('texter.transports'), null]);

        if ($withDefaultBus) {
            $container->register('messenger.bus.default', \stdClass::class)->addTag('messenger.bus');
            $container->setAlias('messenger.default_bus', 'messenger.bus.default');
        }

        return $container;
    }
}
