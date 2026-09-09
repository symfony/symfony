<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Notifier\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Notifier\ChatterInterface;
use Symfony\Component\Notifier\DependencyInjection\RemoveMissingDependenciesPass;
use Symfony\Component\Notifier\NotifierBundle;
use Symfony\Component\Notifier\TexterInterface;

class NotifierBundleTest extends TestCase
{
    private const TRANSPORTS = [
        'notification_on_failed_messages' => true,
        'chatter_transports' => ['slack' => 'null'],
        'texter_transports' => ['twilio' => 'null'],
        'channel_policy' => ['low' => ['slack'], 'high' => ['slack', 'twilio']],
        'admin_recipients' => [['email' => 'test@test.de', 'phone' => '+490815']],
    ];

    public function testTheChannelsAndTransportsAreRegistered()
    {
        $container = $this->load(self::TRANSPORTS, ['mailer', 'messenger']);

        $this->assertTrue($container->hasDefinition('notifier'));
        $this->assertTrue($container->hasDefinition('chatter'));
        $this->assertTrue($container->hasDefinition('texter'));
        $this->assertTrue($container->hasDefinition('notifier.channel.chat'));
        $this->assertTrue($container->hasDefinition('notifier.channel.email'));
        $this->assertTrue($container->hasDefinition('notifier.channel.sms'));
        $this->assertTrue($container->hasDefinition('notifier.channel_policy'));
        $this->assertTrue($container->getDefinition('notifier.failed_message_listener')->hasTag('kernel.event_subscriber'));
    }

    public function testTheEmailChannelIsWiredWithTheMailerEnvelopeSender()
    {
        $container = $this->load(self::TRANSPORTS, ['mailer', 'messenger']);

        $this->assertSame('sender@example.org', $container->getDefinition('notifier.channel.email')->getArgument(2));
    }

    public function testTheEmailChannelIsDroppedWithoutAMailer()
    {
        $container = $this->load(self::TRANSPORTS, ['messenger']);

        $this->assertFalse($container->hasDefinition('notifier.channel.email'));
    }

    public function testTheFailedMessageListenerStaysUnsubscribedWithoutAMessageBus()
    {
        $container = $this->load(self::TRANSPORTS, ['mailer']);

        $this->assertFalse($container->getDefinition('notifier.failed_message_listener')->hasTag('kernel.event_subscriber'));
    }

    public function testTheChannelsDropTheirTransportWhenABusIsRegistered()
    {
        $container = $this->load(self::TRANSPORTS, ['mailer', 'messenger']);

        foreach (['notifier.channel.chat', 'notifier.channel.email', 'notifier.channel.sms'] as $channelId) {
            $this->assertNull($container->getDefinition($channelId)->getArgument(0), $channelId);
        }
    }

    public function testTheChannelsKeepTheirTransportWithoutABus()
    {
        $container = $this->load(self::TRANSPORTS, ['mailer']);

        $this->assertNotNull($container->getDefinition('notifier.channel.chat')->getArgument(0));
    }

    public function testNoTransportsLeavesTheChatterAndTexterUnregistered()
    {
        $container = $this->load();

        $this->assertTrue($container->hasDefinition('notifier'));
        $this->assertFalse($container->hasDefinition('chatter'));
        $this->assertFalse($container->hasAlias(ChatterInterface::class));
        $this->assertFalse($container->hasDefinition('texter'));
        $this->assertFalse($container->hasAlias(TexterInterface::class));
    }

    public function testADisabledMessageBusLeavesTheChannelsWithoutOne()
    {
        $container = $this->load(self::TRANSPORTS + ['message_bus' => false], ['mailer', 'messenger']);

        foreach (['chatter', 'texter', 'notifier.channel.chat', 'notifier.channel.email', 'notifier.channel.sms'] as $id) {
            $this->assertNull($container->getDefinition($id)->getArgument(1), $id);
        }
    }

    public function testASpecificMessageBusIsInjectedInTheChannels()
    {
        $container = $this->load(self::TRANSPORTS + ['message_bus' => 'app.another_bus'], ['mailer', 'messenger']);

        foreach (['chatter', 'texter', 'notifier.channel.chat', 'notifier.channel.email', 'notifier.channel.sms'] as $id) {
            $this->assertEquals(new Reference('app.another_bus'), $container->getDefinition($id)->getArgument(1), $id);
        }
    }

    public function testEveryBridgeHasItsTransportFactoryRegistered()
    {
        if (!class_exists('Symfony\Bundle\FullStack')) {
            $this->markTestSkipped('This test can only run in fullstack test suites.');
        }

        $container = $this->load(self::TRANSPORTS, ['mailer', 'messenger']);

        foreach (scandir(\dirname(__DIR__).'/Bridge') as $bridge) {
            if (\in_array($bridge, ['.', '..', 'Mercure'], true) || !is_dir(\dirname(__DIR__).'/Bridge/'.$bridge)) {
                continue;
            }

            $name = strtolower(preg_replace('/(.)([A-Z])/', '$1-$2', $bridge));
            $this->assertTrue($container->hasDefinition('notifier.transport_factory.'.$name), \sprintf('Did you forget to add the "%s" transport factory to the $classToServices array in NotifierBundle?', $bridge));
        }
    }

    public function testTheNotificationLoggerListenerIsResettable()
    {
        $container = $this->load(self::TRANSPORTS, ['mailer', 'messenger', 'test']);

        // Otherwise a worker keeps every notification it ever sent, and the
        // collector reports the ones from previous messages.
        $this->assertSame([['method' => 'reset']], $container->getDefinition('notifier.notification_logger_listener')->getTag('kernel.reset'));
    }

    #[DataProvider('provideLoggerListenerRegistration')]
    public function testTheNotificationLoggerListenerIsRegisteredOnlyForItsConsumers(array $neighbours, bool $expectedRegistered, bool $expectedGated)
    {
        $container = $this->load(self::TRANSPORTS, array_merge(['mailer', 'messenger'], $neighbours));

        $this->assertSame($expectedRegistered, $container->hasDefinition('notifier.notification_logger_listener'));

        if (!$expectedRegistered) {
            return;
        }

        $arguments = $container->getDefinition('notifier.notification_logger_listener')->getArguments();

        if ($expectedGated) {
            $this->assertEquals(new Reference('profiler.is_disabled_state_checker', ContainerInterface::NULL_ON_INVALID_REFERENCE), $arguments[0]);
        } else {
            $this->assertSame([], $arguments);
        }
    }

    public static function provideLoggerListenerRegistration(): iterable
    {
        // Nothing consumes the retained notifications, so the listener is dropped.
        yield 'neither profiler nor test' => [[], false, false];

        // The profiler consumes them, but only while it is collecting.
        yield 'profiler only' => [['profiler'], true, true];

        // The assertions read the listener directly, so it must always collect.
        yield 'test only' => [['test'], true, false];
        yield 'profiler and test' => [['profiler', 'test'], true, false];
    }

    public function testTheDataCollectorIsDroppedWithoutAProfiler()
    {
        $this->assertFalse($this->load(self::TRANSPORTS, ['mailer', 'messenger'], true)->hasDefinition('notifier.data_collector'));
        $this->assertTrue($this->load(self::TRANSPORTS, ['mailer', 'messenger', 'profiler'], true)->hasDefinition('notifier.data_collector'));
    }

    /**
     * @param list<string> $neighbours services another bundle would have registered
     */
    private function load(array $config = [], array $neighbours = [], bool $debug = false): ContainerBuilder
    {
        $container = new ContainerBuilder(new ParameterBag(['kernel.debug' => $debug, 'kernel.bundles' => []]));
        new NotifierBundle()->getContainerExtension()->load([$config], $container);

        if (\in_array('mailer', $neighbours, true)) {
            $container->register('mailer.envelope_listener')->setArguments(['sender@example.org']);
        }

        if (\in_array('messenger', $neighbours, true)) {
            $container->register('app.message_bus');
            $container->setAlias('messenger.default_bus', 'app.message_bus');
        }

        foreach (['profiler', 'test.client'] as $id) {
            if (\in_array(str_replace('.client', '', $id), $neighbours, true)) {
                $container->register($id);
            }
        }

        new RemoveMissingDependenciesPass()->process($container);

        return $container;
    }
}
