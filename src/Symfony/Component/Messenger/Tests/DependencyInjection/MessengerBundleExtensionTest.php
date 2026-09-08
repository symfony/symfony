<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ExpectUserDeprecationMessageTrait;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\ResolveChildDefinitionsPass;
use Symfony\Component\DependencyInjection\Compiler\ResolveTaggedIteratorArgumentPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\ClosureLoader;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\Attribute\AsMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Bridge\AmazonSqs\Transport\AmazonSqsTransportFactory;
use Symfony\Component\Messenger\Bridge\AmpSql\Transport\AmpSqlTransportFactory;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransportFactory;
use Symfony\Component\Messenger\Bridge\Beanstalkd\Transport\BeanstalkdTransportFactory;
use Symfony\Component\Messenger\Bridge\MongoDb\Transport\MongoDbTransportFactory;
use Symfony\Component\Messenger\Bridge\Redis\Transport\RedisTransportFactory;
use Symfony\Component\Messenger\DependencyInjection\MessengerPass;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\MessengerBundle;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\Serialization\ClaimCheckSerializer;
use Symfony\Component\Messenger\Transport\TransportFactory;

class MessengerBundleExtensionTest extends TestCase
{
    use ExpectUserDeprecationMessageTrait;

    public function testMessengerServicesRemovedWhenDisabled()
    {
        $container = $this->createContainerFromFile('messenger_disabled');
        $messengerDefinitions = array_filter(
            $container->getDefinitions(),
            static fn ($name) => str_starts_with($name, 'messenger.'),
            \ARRAY_FILTER_USE_KEY
        );

        $this->assertSame([], $messengerDefinitions);
        $this->assertFalse($container->hasDefinition('console.command.messenger_consume_messages'));
        $this->assertFalse($container->hasDefinition('console.command.messenger_show'));
        $this->assertFalse($container->hasDefinition('console.command.messenger_debug'));
        $this->assertFalse($container->hasDefinition('console.command.messenger_stop_workers'));
        $this->assertFalse($container->hasDefinition('console.command.messenger_setup_transports'));
        $this->assertFalse($container->hasDefinition('console.command.messenger_failed_messages_retry'));
        $this->assertFalse($container->hasDefinition('console.command.messenger_failed_messages_show'));
        $this->assertFalse($container->hasDefinition('console.command.messenger_failed_messages_remove'));
        $this->assertFalse($container->hasDefinition('serializer.normalizer.flatten_exception'));
    }

    public function testMessenger()
    {
        $container = $this->createContainerFromFile('messenger', false);
        $container->addCompilerPass(new ResolveTaggedIteratorArgumentPass());
        $container->compile();

        $expectedFactories = [];

        if (class_exists(AmqpTransportFactory::class)) {
            $expectedFactories[] = 'messenger.transport.amqp.factory';
        }

        if (class_exists(AmpSqlTransportFactory::class)) {
            $expectedFactories[] = 'messenger.transport.amp_sql.factory';
        }

        if (class_exists(RedisTransportFactory::class)) {
            $expectedFactories[] = 'messenger.transport.redis.factory';
        }

        $expectedFactories[] = 'messenger.transport.sync.factory';
        $expectedFactories[] = 'messenger.transport.in_memory.factory';

        if (class_exists(AmazonSqsTransportFactory::class)) {
            $expectedFactories[] = 'messenger.transport.sqs.factory';
        }

        if (class_exists(BeanstalkdTransportFactory::class)) {
            $expectedFactories[] = 'messenger.transport.beanstalkd.factory';
        }

        if (class_exists(MongoDbTransportFactory::class)) {
            $expectedFactories[] = 'messenger.transport.mongodb.factory';
        }

        $this->assertTrue($container->hasDefinition('messenger.receiver_locator'));
        $this->assertTrue($container->hasDefinition('console.command.messenger_consume_messages'));
        $this->assertTrue($container->hasAlias('messenger.default_bus'));
        $this->assertTrue($container->getAlias('messenger.default_bus')->isPublic());
        $this->assertTrue($container->hasAlias(MessageBusInterface::class));
        $this->assertFalse($container->getAlias(MessageBusInterface::class)->isPublic());
        $this->assertTrue($container->hasDefinition('messenger.transport_factory'));
        $this->assertSame(TransportFactory::class, $container->getDefinition('messenger.transport_factory')->getClass());
        $this->assertInstanceOf(TaggedIteratorArgument::class, $container->getDefinition('messenger.transport_factory')->getArgument(0));
        $this->assertEquals($expectedFactories, $container->getDefinition('messenger.transport_factory')->getArgument(0)->getValues());
        $this->assertTrue($container->hasDefinition('messenger.listener.reset_services'));
        $this->assertSame('messenger.listener.reset_services', (string) $container->getDefinition('console.command.messenger_consume_messages')->getArgument(5));
        $this->assertSame('%kernel.project_dir%/bin/console', $container->getDefinition('console.command.messenger_consume_messages')->getArgument(9));
    }

    public function testMessengerAsMessageAttributeIsForwardedToTheTag()
    {
        $container = $this->createContainerFromFile('messenger', false);
        $container->compile();

        $configurators = $container->getAttributeAutoconfigurators()[AsMessage::class] ?? [];
        $this->assertCount(1, $configurators);

        $definition = new ChildDefinition('');
        $configurators[0]($definition, new AsMessage(serializedTypeName: 'my.type', serializedTypeNameAliases: ['my.legacy.type']));

        $this->assertSame(
            [['transport' => null, 'serializedTypeName' => 'my.type', 'serializedTypeNameAliases' => ['my.legacy.type']]],
            $definition->getTag('messenger.message')
        );
    }

    public function testMessengerAsMessageHandlerTransportIsForwardedToTheTag()
    {
        $container = $this->createContainerFromFile('messenger', false);
        $container->compile();

        $configurators = $container->getAttributeAutoconfigurators()[AsMessageHandler::class] ?? [];
        $this->assertCount(1, $configurators);

        $definition = new ChildDefinition('');
        $configurators[0]($definition, new AsMessageHandler(transport: 'async'), new \ReflectionClass(DummyMessage::class));

        $this->assertSame([['bus' => null, 'handles' => null, 'method' => null, 'priority' => 0, 'sign' => false, 'transport' => 'async', 'from_transport' => null]], $definition->getTag('messenger.message_handler'));
    }

    public function testMessengerRejectRedeliveredMessagesEnabledByDefault()
    {
        $container = $this->createContainerFromFile('messenger', false);
        $container->compile();

        $this->assertContains(
            ['id' => 'reject_redelivered_message_middleware'],
            $container->getParameter('messenger.bus.default.middleware')
        );
    }

    public function testMessengerRejectRedeliveredMessagesCanBeDisabled()
    {
        $container = $this->createContainerFromFile('messenger_reject_redelivered_messages_disabled', false);
        $container->compile();

        $this->assertNotContains(
            ['id' => 'reject_redelivered_message_middleware'],
            $container->getParameter('messenger.bus.default.middleware')
        );
        $this->assertTrue($container->hasDefinition('messenger.middleware.reject_redelivered_message_middleware'));
    }

    public function testMessengerRejectRedeliveredMessagesCanStillBeListedOnABusWhenDisabled()
    {
        $container = $this->createContainerFromFile('messenger_reject_redelivered_messages_disabled_explicit_bus', false);
        $container->addCompilerPass(new MessengerPass());
        $container->compile();

        $this->assertNotContains('messenger.middleware.reject_redelivered_message_middleware', $this->getBusMiddlewareIds($container, 'messenger.bus.default'));
        $this->assertContains('messenger.middleware.reject_redelivered_message_middleware', $this->getBusMiddlewareIds($container, 'messenger.bus.commands'));
    }

    public function testMessengerMultipleFailureTransports()
    {
        $container = $this->createContainerFromFile('messenger_multiple_failure_transports', false);
        $container->addCompilerPass(new MessengerPass());
        $container->compile();

        $failureTransport1Definition = $container->getDefinition('messenger.transport.failure_transport_1');
        $failureTransport1Tags = $failureTransport1Definition->getTag('messenger.receiver')[0];

        $this->assertEquals([
            'alias' => 'failure_transport_1',
            'is_failure_transport' => true,
            'priority' => 0,
        ], $failureTransport1Tags);

        $failureTransport3Definition = $container->getDefinition('messenger.transport.failure_transport_3');
        $failureTransport3Tags = $failureTransport3Definition->getTag('messenger.receiver')[0];

        $this->assertEquals([
            'alias' => 'failure_transport_3',
            'is_failure_transport' => true,
            'priority' => 0,
        ], $failureTransport3Tags);

        // transport 2 exists but does not appear in the mapping
        $this->assertFalse($container->hasDefinition('messenger.transport.failure_transport_2'));

        $failureTransportsByTransportNameServiceLocator = $container->getDefinition('messenger.failure.send_failed_message_to_failure_transport_listener')->getArgument(0);
        $failureTransports = $container->getDefinition((string) $failureTransportsByTransportNameServiceLocator)->getArgument(0);
        $expectedTransportsByFailureTransports = [
            'transport_1' => new Reference('messenger.transport.failure_transport_1'),
            'transport_3' => new Reference('messenger.transport.failure_transport_3'),
        ];

        $failureTransportsReferences = array_map(static function (ServiceClosureArgument $serviceClosureArgument) {
            $values = $serviceClosureArgument->getValues();

            return array_shift($values);
        }, $failureTransports);
        $this->assertEquals($expectedTransportsByFailureTransports, $failureTransportsReferences);
        $this->assertSame([
            'transport_1' => 'failure_transport_1',
            'transport_3' => 'failure_transport_3',
        ], $container->getDefinition('console.command.messenger_debug')->getArgument(4));
    }

    public function testMessengerMultipleFailureTransportsWithGlobalFailureTransport()
    {
        $container = $this->createContainerFromFile('messenger_multiple_failure_transports_global', false);
        $container->addCompilerPass(new MessengerPass());
        $container->compile();

        $this->assertEquals('messenger.transport.failure_transport_global', (string) $container->getAlias('messenger.failure_transports.default'));

        $failureTransport1Definition = $container->getDefinition('messenger.transport.failure_transport_1');
        $failureTransport1Tags = $failureTransport1Definition->getTag('messenger.receiver')[0];

        $this->assertEquals([
            'alias' => 'failure_transport_1',
            'is_failure_transport' => true,
            'priority' => 0,
        ], $failureTransport1Tags);

        $failureTransport3Definition = $container->getDefinition('messenger.transport.failure_transport_3');
        $failureTransport3Tags = $failureTransport3Definition->getTag('messenger.receiver')[0];

        $this->assertEquals([
            'alias' => 'failure_transport_3',
            'is_failure_transport' => true,
            'priority' => 0,
        ], $failureTransport3Tags);

        $failureTransportsByTransportNameServiceLocator = $container->getDefinition('messenger.failure.send_failed_message_to_failure_transport_listener')->getArgument(0);
        $failureTransports = $container->getDefinition((string) $failureTransportsByTransportNameServiceLocator)->getArgument(0);
        $expectedTransportsByFailureTransports = [
            'failure_transport_1' => new Reference('messenger.transport.failure_transport_global'),
            'failure_transport_3' => new Reference('messenger.transport.failure_transport_global'),
            'failure_transport_global' => new Reference('messenger.transport.failure_transport_global'),
            'transport_1' => new Reference('messenger.transport.failure_transport_1'),
            'transport_2' => new Reference('messenger.transport.failure_transport_global'),
            'transport_3' => new Reference('messenger.transport.failure_transport_3'),
        ];

        $failureTransportsReferences = array_map(static function (ServiceClosureArgument $serviceClosureArgument) {
            $values = $serviceClosureArgument->getValues();

            return array_shift($values);
        }, $failureTransports);
        $this->assertEquals($expectedTransportsByFailureTransports, $failureTransportsReferences);
        $this->assertSame([
            'transport_1' => 'failure_transport_1',
            'transport_2' => 'failure_transport_global',
            'transport_3' => 'failure_transport_3',
            'failure_transport_global' => 'failure_transport_global',
            'failure_transport_1' => 'failure_transport_global',
            'failure_transport_3' => 'failure_transport_global',
        ], $container->getDefinition('console.command.messenger_debug')->getArgument(4));
    }

    public function testMessengerTransports()
    {
        $container = $this->createContainerFromFile('messenger_transports');
        $this->assertTrue($container->hasDefinition('messenger.transport.default'));
        $this->assertTrue($container->getDefinition('messenger.transport.default')->hasTag('messenger.receiver'));
        $this->assertEquals([[
            'alias' => 'default',
            'is_failure_transport' => false,
            'priority' => 0,
        ]], $container->getDefinition('messenger.transport.default')->getTag('messenger.receiver'));
        $transportArguments = $container->getDefinition('messenger.transport.default')->getArguments();
        $this->assertEquals(new Reference('messenger.default_serializer'), $transportArguments[2]);

        $this->assertTrue($container->hasDefinition('messenger.transport.customised'));
        $transportFactory = $container->getDefinition('messenger.transport.customised')->getFactory();
        $transportArguments = $container->getDefinition('messenger.transport.customised')->getArguments();

        $this->assertTrue($container->hasDefinition('messenger.transport.prioritized'));
        $this->assertTrue($container->getDefinition('messenger.transport.prioritized')->hasTag('messenger.receiver'));
        $this->assertEquals([[
            'alias' => 'prioritized',
            'is_failure_transport' => false,
            'priority' => 10,
        ]], $container->getDefinition('messenger.transport.prioritized')->getTag('messenger.receiver'));

        $this->assertEquals([new Reference('messenger.transport_factory'), 'createTransport'], $transportFactory);
        $this->assertCount(3, $transportArguments);
        $this->assertSame('amqp://localhost/%2f/messages?exchange_name=exchange_name', $transportArguments[0]);
        $this->assertEquals(['queue' => ['name' => 'Queue'], 'transport_name' => 'customised'], $transportArguments[1]);
        $this->assertEquals(new Reference('messenger.transport.native_php_serializer'), $transportArguments[2]);

        $this->assertTrue($container->hasDefinition('messenger.transport.amqp.factory'));

        $this->assertTrue($container->hasDefinition('messenger.transport.redis'));
        $transportFactory = $container->getDefinition('messenger.transport.redis')->getFactory();
        $transportArguments = $container->getDefinition('messenger.transport.redis')->getArguments();

        $this->assertEquals([new Reference('messenger.transport_factory'), 'createTransport'], $transportFactory);
        $this->assertCount(3, $transportArguments);
        $this->assertSame('redis://127.0.0.1:6379/messages', $transportArguments[0]);

        $this->assertTrue($container->hasDefinition('messenger.transport.redis.factory'));

        $this->assertTrue($container->hasDefinition('messenger.transport.beanstalkd'));
        $transportFactory = $container->getDefinition('messenger.transport.beanstalkd')->getFactory();
        $transportArguments = $container->getDefinition('messenger.transport.beanstalkd')->getArguments();

        $this->assertEquals([new Reference('messenger.transport_factory'), 'createTransport'], $transportFactory);
        $this->assertCount(3, $transportArguments);
        $this->assertSame('beanstalkd://127.0.0.1:11300', $transportArguments[0]);

        $this->assertTrue($container->hasDefinition('messenger.transport.beanstalkd.factory'));

        $this->assertTrue($container->hasDefinition('messenger.transport.schedule'));
        $transportFactory = $container->getDefinition('messenger.transport.schedule')->getFactory();
        $transportArguments = $container->getDefinition('messenger.transport.schedule')->getArguments();

        $this->assertEquals([new Reference('messenger.transport_factory'), 'createTransport'], $transportFactory);
        $this->assertCount(3, $transportArguments);
        $this->assertSame('schedule://default', $transportArguments[0]);

        $this->assertSame(10, $container->getDefinition('messenger.retry.multiplier_retry_strategy.customised')->getArgument(0));
        $this->assertSame(7, $container->getDefinition('messenger.retry.multiplier_retry_strategy.customised')->getArgument(1));
        $this->assertSame(3, $container->getDefinition('messenger.retry.multiplier_retry_strategy.customised')->getArgument(2));
        $this->assertSame(100, $container->getDefinition('messenger.retry.multiplier_retry_strategy.customised')->getArgument(3));

        $failureTransportsByTransportNameServiceLocator = $container->getDefinition('messenger.failure.send_failed_message_to_failure_transport_listener')->getArgument(0);
        $failureTransports = $container->getDefinition((string) $failureTransportsByTransportNameServiceLocator)->getArgument(0);
        $expectedTransportsByFailureTransports = [
            'prioritized' => new Reference('messenger.transport.failed'),
            'beanstalkd' => new Reference('messenger.transport.failed'),
            'customised' => new Reference('messenger.transport.failed'),
            'default' => new Reference('messenger.transport.failed'),
            'failed' => new Reference('messenger.transport.failed'),
            'redis' => new Reference('messenger.transport.failed'),
            'schedule' => new Reference('messenger.transport.failed'),
        ];

        $failureTransportsReferences = array_map(static function (ServiceClosureArgument $serviceClosureArgument) {
            $values = $serviceClosureArgument->getValues();

            return array_shift($values);
        }, $failureTransports);
        $this->assertEquals($expectedTransportsByFailureTransports, $failureTransportsReferences);

        $rateLimitedTransports = $container->getDefinition('messenger.rate_limiter_locator')->getArgument(0);
        $expectedRateLimitersByRateLimitedTransports = [
            'customised' => new Reference('limiter.customised_worker'),
        ];
        $this->assertEquals($expectedRateLimitersByRateLimitedTransports, $rateLimitedTransports);
    }

    public function testMessengerClaimCheckSerializer()
    {
        $container = $this->createContainerFromFile('messenger_claim_check');

        $transport = $container->getDefinition('messenger.transport.async');
        $this->assertSame('.messenger.transport.async.claim_check_serializer', (string) $transport->getArgument(2));

        $serializer = $container->getDefinition('.messenger.transport.async.claim_check_serializer');
        $this->assertSame(ClaimCheckSerializer::class, $serializer->getClass());
        $this->assertSame('messenger.default_serializer', (string) $serializer->getArgument(0));
        $this->assertSame('app.claim_check_pool', (string) $serializer->getArgument(1));
        $this->assertSame(200000, $serializer->getArgument(2));

        $serializers = $container->getDefinition('messenger.transport.serializer_locator')->getArgument(0);
        $this->assertSame('.messenger.transport.async.claim_check_serializer', (string) $serializers['async']);

        // signing must decorate the inner serializer, never the claim check wrapper
        $eligible = $container->getDefinition('messenger.signing_serializer')->getArgument(2)['*'];
        $this->assertContains('messenger.default_serializer', $eligible);
        $this->assertNotContains('.messenger.transport.async.claim_check_serializer', $eligible);
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testLegacyMessengerRouting()
    {
        $this->expectUserDeprecationMessage('Since symfony/framework-bundle 8.1: Using the "senders" nesting level for messenger routing configuration is deprecated and will be removed in version 9.0. Use a flat list of senders instead.');

        $container = $this->createContainerFromFile('messenger_routing_legacy_senders');
        $senderLocatorDefinition = $container->getDefinition('messenger.senders_locator');

        $sendersMapping = $senderLocatorDefinition->getArgument(0);
        $this->assertEquals(['amqp', 'messenger.transport.audit'], $sendersMapping[DummyMessage::class]);
        $sendersLocator = $container->getDefinition((string) $senderLocatorDefinition->getArgument(1));
        $this->assertSame(['amqp', 'audit', 'messenger.transport.amqp', 'messenger.transport.audit'], array_keys($sendersLocator->getArgument(0)));
        $this->assertEquals(new Reference('messenger.transport.amqp'), $sendersLocator->getArgument(0)['amqp']->getValues()[0]);
        $this->assertEquals(new Reference('messenger.transport.audit'), $sendersLocator->getArgument(0)['messenger.transport.audit']->getValues()[0]);
    }

    public function testMessengerRouting()
    {
        $container = $this->createContainerFromFile('messenger_routing');
        $senderLocatorDefinition = $container->getDefinition('messenger.senders_locator');

        $sendersMapping = $senderLocatorDefinition->getArgument(0);
        $this->assertEquals(['amqp', 'messenger.transport.audit'], $sendersMapping[DummyMessage::class]);
        $sendersLocator = $container->getDefinition((string) $senderLocatorDefinition->getArgument(1));
        $this->assertSame(['amqp', 'audit', 'messenger.transport.amqp', 'messenger.transport.audit'], array_keys($sendersLocator->getArgument(0)));
        $this->assertEquals(new Reference('messenger.transport.amqp'), $sendersLocator->getArgument(0)['amqp']->getValues()[0]);
        $this->assertEquals(new Reference('messenger.transport.audit'), $sendersLocator->getArgument(0)['messenger.transport.audit']->getValues()[0]);
    }

    public function testMessengerRoutingSingle()
    {
        $container = $this->createContainerFromFile('messenger_routing_single');
        $senderLocatorDefinition = $container->getDefinition('messenger.senders_locator');

        $sendersMapping = $senderLocatorDefinition->getArgument(0);
        $this->assertEquals(['amqp'], $sendersMapping[DummyMessage::class]);
    }

    public function testAsMessageAutoconfigurationUsesResourceTag()
    {
        $container = $this->createContainerFromFile('messenger', false);
        $container->compile();

        $this->assertArrayHasKey(AsMessage::class, $container->getAttributeAutoconfigurators());
        $this->assertCount(1, $container->getAttributeAutoconfigurators()[AsMessage::class]);

        $definition = new ChildDefinition('foo');
        $autoconfigurator = $container->getAttributeAutoconfigurators()[AsMessage::class][0];
        $autoconfigurator($definition, new AsMessage(transport: ['async']));

        $this->assertSame([['transport' => ['async'], 'serializedTypeName' => null, 'serializedTypeNameAliases' => []]], $definition->getTag('messenger.message'));
        $this->assertSame([['source' => 'by tag "messenger.message"']], $definition->getTag('container.excluded'));
    }

    public function testMessengerTransportConfiguration()
    {
        $container = $this->createContainerFromFile('messenger_transport');

        $this->assertSame('messenger.transport.symfony_serializer', (string) $container->getAlias('messenger.default_serializer'));

        $serializerTransportDefinition = $container->getDefinition('messenger.transport.symfony_serializer');
        $this->assertSame('csv', $serializerTransportDefinition->getArgument(1));
        $this->assertSame(['enable_max_depth' => true], $serializerTransportDefinition->getArgument(2));
    }

    public function testMessengerWithAddBusNameStampMiddleware()
    {
        $container = $this->createContainerFromFile('messenger_bus_name_stamp');

        $this->assertTrue($container->has('messenger.bus.commands'));
        $this->assertEquals([
            ['id' => 'add_bus_name_stamp_middleware', 'arguments' => ['messenger.bus.commands']],
            ['id' => 'send_message', 'arguments' => []],
            ['id' => 'handle_message', 'arguments' => []],
        ], $container->getParameter('messenger.bus.commands.middleware'));
        $this->assertTrue($container->has('messenger.bus.events'));
        $this->assertSame([], $container->getDefinition('messenger.bus.events')->getArgument(0));
        $this->assertEquals([
            ['id' => 'add_default_stamps_middleware'],
            ['id' => 'add_bus_name_stamp_middleware', 'arguments' => ['messenger.bus.events']],
            ['id' => 'reject_redelivered_message_middleware'],
            ['id' => 'dispatch_after_current_bus'],
            ['id' => 'decode_failed_message_middleware'],
            ['id' => 'failed_message_processing_middleware'],
            ['id' => 'deduplicate_middleware'],
            ['id' => 'send_message', 'arguments' => [true]],
            ['id' => 'handle_message', 'arguments' => ['index_1' => false]],
        ], $container->getParameter('messenger.bus.events.middleware'));
    }

    public function testMessengerWithMultipleBuses()
    {
        $container = $this->createContainerFromFile('messenger_multiple_buses');

        $this->assertTrue($container->has('messenger.bus.commands'));
        $this->assertSame([], $container->getDefinition('messenger.bus.commands')->getArgument(0));
        $this->assertEquals([
            ['id' => 'add_default_stamps_middleware'],
            ['id' => 'add_bus_name_stamp_middleware', 'arguments' => ['messenger.bus.commands']],
            ['id' => 'reject_redelivered_message_middleware'],
            ['id' => 'dispatch_after_current_bus'],
            ['id' => 'decode_failed_message_middleware'],
            ['id' => 'failed_message_processing_middleware'],
            ['id' => 'deduplicate_middleware'],
            ['id' => 'send_message', 'arguments' => [true]],
            ['id' => 'handle_message', 'arguments' => ['index_1' => false]],
        ], $container->getParameter('messenger.bus.commands.middleware'));
        $this->assertTrue($container->has('messenger.bus.events'));
        $this->assertSame([], $container->getDefinition('messenger.bus.events')->getArgument(0));
        $this->assertEquals([
            ['id' => 'add_default_stamps_middleware'],
            ['id' => 'add_bus_name_stamp_middleware', 'arguments' => ['messenger.bus.events']],
            ['id' => 'reject_redelivered_message_middleware'],
            ['id' => 'dispatch_after_current_bus'],
            ['id' => 'decode_failed_message_middleware'],
            ['id' => 'failed_message_processing_middleware'],
            ['id' => 'deduplicate_middleware'],
            ['id' => 'with_factory', 'arguments' => ['foo', true, ['bar' => 'baz']]],
            ['id' => 'send_message', 'arguments' => [true]],
            ['id' => 'handle_message', 'arguments' => ['index_1' => false]],
        ], $container->getParameter('messenger.bus.events.middleware'));
        $this->assertTrue($container->has('messenger.bus.queries'));
        $this->assertSame([], $container->getDefinition('messenger.bus.queries')->getArgument(0));
        $this->assertEquals([
            ['id' => 'send_message', 'arguments' => []],
            ['id' => 'handle_message', 'arguments' => []],
        ], $container->getParameter('messenger.bus.queries.middleware'));

        $this->assertTrue($container->hasAlias('messenger.default_bus'));
        $this->assertSame('messenger.bus.commands', (string) $container->getAlias('messenger.default_bus'));
    }

    public function testMessengerMiddlewareFactoryErroneousFormat()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid middleware at path "messenger": a map with a single factory id as key and its arguments as value was expected, {"foo":["qux"],"bar":["baz"]} given.');
        $this->createContainerFromFile('messenger_middleware_factory_erroneous_format');
    }

    public function testMessengerInvalidTransportRouting()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Invalid Messenger routing configuration: invalid namespace "Symfony\*\DummyMessage" wildcard.');
        $this->createContainerFromFile('messenger_routing_invalid_wildcard');
    }

    public function testMessengerInvalidWildcardRouting()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Invalid Messenger routing configuration: the "Symfony\Component\Messenger\Tests\Fixtures\DummyMessage" class is being routed to a sender called "invalid". This is not a valid transport or service id.');
        $this->createContainerFromFile('messenger_routing_invalid_transport');
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testLegacyMessengerSigningSerializerWiring()
    {
        $this->expectUserDeprecationMessage('Since symfony/framework-bundle 8.1: Using the "senders" nesting level for messenger routing configuration is deprecated and will be removed in version 9.0. Use a flat list of senders instead.');

        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->register('signed_handler', 'stdClass')
                ->addTag('messenger.message_handler', ['handles' => DummyMessage::class, 'sign' => true]);

            $container->loadFromExtension('messenger', [
                'transports' => [
                    'async' => ['dsn' => 'in-memory://'],
                ],
                'routing' => [
                    DummyMessage::class => ['senders' => ['async']],
                ],
                'buses' => [
                    'message_bus' => ['default_middleware' => ['enabled' => true]],
                ],
            ]);
        });

        $this->assertTrue($container->hasDefinition('messenger.signing_serializer'));
        $mapping = $container->getDefinition('messenger.signing_serializer')->getArgument(2);
        $this->assertArrayHasKey(DummyMessage::class, $mapping);
        $this->assertNotEmpty($mapping[DummyMessage::class]);

        $this->assertTrue($container->hasDefinition('message_bus'));
        $this->assertSame('message_bus', (string) $container->getAlias('messenger.default_bus'));
    }

    public function testMessengerSigningSerializerWiring()
    {
        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->register('signed_handler', 'stdClass')
                ->addTag('messenger.message_handler', ['handles' => DummyMessage::class, 'sign' => true]);

            $container->loadFromExtension('messenger', [
                'transports' => [
                    'async' => ['dsn' => 'in-memory://'],
                ],
                'routing' => [
                    DummyMessage::class => ['async'],
                ],
                'buses' => [
                    'message_bus' => ['default_middleware' => ['enabled' => true]],
                ],
            ]);
        });

        $this->assertTrue($container->hasDefinition('messenger.signing_serializer'));
        $mapping = $container->getDefinition('messenger.signing_serializer')->getArgument(2);
        $this->assertArrayHasKey(DummyMessage::class, $mapping);
        $this->assertNotEmpty($mapping[DummyMessage::class]);

        $this->assertTrue($container->hasDefinition('message_bus'));
        $this->assertSame('message_bus', (string) $container->getAlias('messenger.default_bus'));
    }

    public function testMessengerSigningSerializerWiringForUnroutedMessages()
    {
        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->register('signed_handler', 'stdClass')
                ->addTag('messenger.message_handler', ['handles' => DummyMessage::class, 'sign' => true]);

            $container->loadFromExtension('messenger', [
                'transports' => [
                    'async' => ['dsn' => 'in-memory://'],
                ],
                'routing' => [],
                'buses' => [
                    'message_bus' => ['default_middleware' => ['enabled' => true]],
                ],
            ]);
        });

        $this->assertTrue($container->hasDefinition('messenger.signing_serializer'));
        $mapping = $container->getDefinition('messenger.signing_serializer')->getArgument(2);
        $this->assertArrayHasKey('*', $mapping);
        $this->assertContains('messenger.default_serializer', $mapping['*']);
    }

    private function getBusMiddlewareIds(ContainerBuilder $container, string $busId): array
    {
        return array_map('strval', $container->getDefinition($busId)->getArgument(0)->getValues());
    }

    private function createContainerFromClosure(\Closure $closure): ContainerBuilder
    {
        $container = $this->createContainer();
        new ClosureLoader($container)->load($closure);
        $container->compile();

        return $container;
    }

    private function createContainerFromFile(string $file, bool $compile = true): ContainerBuilder
    {
        $container = $this->createContainer();
        new PhpFileLoader($container, new FileLocator(__DIR__.'/Fixtures'))->load($file.'.php');

        if ($compile) {
            $container->compile();
        }

        return $container;
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder(new EnvPlaceholderParameterBag([
            'kernel.debug' => false,
            'kernel.project_dir' => __DIR__,
            'kernel.secret' => 's3cr3t',
        ]));
        $container->registerExtension(new MessengerBundle()->getContainerExtension());
        // stands in for CacheBundle, which owns the parent of the worker-restart pool
        $container->register('cache.app', \stdClass::class);
        $container->getCompilerPassConfig()->setBeforeOptimizationPasses([]);
        $container->getCompilerPassConfig()->setOptimizationPasses([new ResolveChildDefinitionsPass()]);
        $container->getCompilerPassConfig()->setBeforeRemovingPasses([]);
        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->getCompilerPassConfig()->setAfterRemovingPasses([]);

        return $container;
    }
}
