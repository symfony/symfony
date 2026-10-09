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

use PHPUnit\Framework\Attributes\DataProvider;
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
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\ClosureLoader;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Parameter;
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
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Failure\FailedMessageRepository;
use Symfony\Component\Messenger\Message\RedispatchMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\MessengerBundle;
use Symfony\Component\Messenger\Middleware\FlowContextMiddleware;
use Symfony\Component\Messenger\Middleware\StackMiddleware;
use Symfony\Component\Messenger\Stamp\MessageIdStamp;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\Messenger\Transport\Sender\OutboxSender;
use Symfony\Component\Messenger\Transport\Serialization\ClaimCheckSerializer;
use Symfony\Component\Messenger\Transport\Serialization\InteropSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SigningSerializer;
use Symfony\Component\Messenger\Transport\TransportFactory;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Uid\UuidV7;

class MessengerBundleExtensionTest extends TestCase
{
    use ExpectUserDeprecationMessageTrait;

    private const DEDUPLICATION_SERVICES = [
        'messenger.middleware.deduplicate_middleware',
        'messenger.failure.release_deduplication_lock_on_failure_listener',
    ];

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
        $this->assertFalse($container->hasDefinition('serializer.normalizer.message_stamp'));
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

        $syncFactoryArguments = $container->getDefinition('messenger.transport.sync.factory')->getArguments();
        $this->assertEquals(new Reference('messenger.retry_strategy_locator'), $syncFactoryArguments[1]);
        $this->assertSame([], $container->getDefinition((string) $syncFactoryArguments[2])->getArgument(0));
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

        $this->assertSame([['bus' => null, 'handles' => null, 'method' => null, 'priority' => null, 'sign' => false, 'transport' => 'async', 'before' => null, 'after' => null, 'from_transport' => null]], $definition->getTag('messenger.message_handler'));
    }

    public function testMessengerAsMessageHandlerBeforeAndAfterAreForwardedToTheTag()
    {
        $container = $this->createContainerFromFile('messenger', false);
        $container->compile();

        $definition = new ChildDefinition('');
        $container->getAttributeAutoconfigurators()[AsMessageHandler::class][0]($definition, new AsMessageHandler(before: 'a', after: ['b', 'c::handle']), new \ReflectionClass(DummyMessage::class));

        $this->assertSame('a', $definition->getTag('messenger.message_handler')[0]['before']);
        $this->assertSame(['b', 'c::handle'], $definition->getTag('messenger.message_handler')[0]['after']);
    }

    public function testMessengerChainMiddleware()
    {
        $container = $this->createContainerFromFile('messenger', false);
        $container->addCompilerPass(new MessengerPass());
        $container->compile();

        $this->assertSame('messenger.routable_message_bus', (string) $container->getDefinition('messenger.middleware.chain')->getArgument(0));
        $this->assertSame('messenger.senders_locator', (string) $container->getDefinition('messenger.middleware.chain')->getArgument(1));
        $this->assertContains('messenger.middleware.chain', $this->getBusMiddlewareIds($container, 'messenger.bus.default'));
        $this->assertTrue($container->getDefinition('serializer.normalizer.message_stamp')->hasTag('serializer.normalizer'));
    }

    public function testMessengerDispatchOnFailure()
    {
        $container = $this->createContainerFromFile('messenger', false);
        $container->addCompilerPass(new MessengerPass());
        $container->compile();

        $middleware = $container->getDefinition('messenger.middleware.dispatch_on_failure');
        $this->assertSame('messenger.routable_message_bus', (string) $middleware->getArgument(0));
        $this->assertEquals(new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE), $middleware->getArgument(1));
        $this->assertContains('messenger.middleware.dispatch_on_failure', $this->getBusMiddlewareIds($container, 'messenger.bus.default'));

        $listener = $container->getDefinition('messenger.failure.dispatch_on_failure_listener');
        $this->assertEquals(new Reference('messenger.routable_message_bus'), $listener->getArgument(0));
        $this->assertEquals(new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE), $listener->getArgument(1));
        $this->assertTrue($listener->hasTag('kernel.event_subscriber'));
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

    public function testMessengerIdentityStampsAreDisabledByDefault()
    {
        $container = $this->createContainerFromFile('messenger', false);
        $container->compile();

        $this->assertFalse($container->getDefinition('messenger.middleware.flow_context')->getArgument(0));
        $this->assertFalse($container->hasDefinition('messenger.message_id_generator'));
    }

    public function testMessengerIdentityStampsAreEnabledOnTheFlowContextMiddleware()
    {
        $container = $this->createContainerFromFile('messenger_identity_stamps', false);
        $container->compile();

        $this->assertTrue($container->getDefinition('messenger.middleware.flow_context')->getArgument(0));
    }

    public function testMessengerFlowContextMiddlewareRunsBetweenDecodingFailedMessagesAndDispatchAfterCurrentBus()
    {
        $container = $this->createContainerFromFile('messenger', false);
        $container->compile();

        $middleware = array_values($container->getParameter('messenger.bus.default.middleware'));
        $position = array_search(['id' => 'flow_context'], $middleware, true);

        $this->assertNotFalse($position, 'The flow_context middleware is listed.');
        $this->assertSame(['id' => 'decode_failed_message_middleware'], $middleware[$position - 1]);
        $this->assertGreaterThan($position, array_search(['id' => 'dispatch_after_current_bus'], $middleware, true));
        $this->assertSame(FlowContextMiddleware::class, $container->getDefinition('messenger.middleware.flow_context')->getClass());
    }

    public function testMessengerDispatchOnFailureMiddlewareRunsBetweenFlowContextAndDispatchAfterCurrentBus()
    {
        $container = $this->createContainerFromFile('messenger', false);
        $container->compile();

        $middleware = array_values($container->getParameter('messenger.bus.default.middleware'));
        $position = array_search(['id' => 'dispatch_on_failure'], $middleware, true);

        $this->assertNotFalse($position, 'The dispatch_on_failure middleware is listed.');
        $this->assertSame(['id' => 'flow_context'], $middleware[$position - 1]);
        $this->assertSame(['id' => 'dispatch_after_current_bus'], $middleware[$position + 1]);
    }

    public function testMessengerIdentityStampsUseUuidV7WhenTheUidComponentIsInstalled()
    {
        if (!class_exists(Uuid::class)) {
            $this->markTestSkipped('The Uid component is not installed.');
        }

        $container = $this->createContainerFromFile('messenger_identity_stamps', false);
        $container->register('foo', \stdClass::class)
            ->setPublic(true)
            ->setProperty('middleware', new Reference('messenger.middleware.flow_context'));
        $container->compile();

        $envelope = $container->get('foo')->middleware->handle(new Envelope(new \stdClass()), new StackMiddleware());

        $this->assertInstanceOf(UuidV7::class, Uuid::fromString($envelope->last(MessageIdStamp::class)->getId()));
    }

    public function testMessengerFlowContextMiddlewareReferencesTheMessageIdGeneratorOptionally()
    {
        $container = $this->createContainerFromFile('messenger_identity_stamps', false);
        $container->compile();

        $this->assertEquals(new Reference('messenger.message_id_generator', ContainerInterface::NULL_ON_INVALID_REFERENCE), $container->getDefinition('messenger.middleware.flow_context')->getArgument(1));
    }

    public function testMessengerFlowContextMiddlewareIsSharedByAllBuses()
    {
        $container = $this->createContainerFromFile('messenger_reject_redelivered_messages_disabled_explicit_bus', false);
        $container->addCompilerPass(new MessengerPass());
        $container->compile();

        $this->assertContains('messenger.middleware.flow_context', $this->getBusMiddlewareIds($container, 'messenger.bus.default'));
        $this->assertContains('messenger.middleware.flow_context', $this->getBusMiddlewareIds($container, 'messenger.bus.commands'));
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

    public function testItRegistersTheFailedMessageRepository()
    {
        $container = $this->createContainerFromFile('messenger_multiple_failure_transports_global', false);
        $container->addCompilerPass(new MessengerPass());
        $container->compile();

        $definition = $container->getDefinition('messenger.failed_message_repository');

        $this->assertSame('failure_transport_global', $definition->getArgument(1));

        $locator = $container->getDefinition((string) $definition->getArgument(0));
        $this->assertSame(
            ['failure_transport_global', 'failure_transport_1', 'failure_transport_3'],
            array_keys($locator->getArgument(0))
        );
    }

    public function testTheFailedMessageRepositoryIsRemovedWithoutAnyFailureTransport()
    {
        $container = $this->createContainerFromFile('messenger', false);
        $container->addCompilerPass(new MessengerPass());
        $container->compile();

        $this->assertFalse($container->has('messenger.failed_message_repository'));
        $this->assertFalse($container->has(FailedMessageRepository::class));
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
        $this->assertEquals(new Reference('.messenger.transport.default.signing_serializer'), $transportArguments[2]);
        $this->assertEquals(new Reference('messenger.default_serializer'), $container->getDefinition('.messenger.transport.default.signing_serializer')->getArgument(0));

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
        $this->assertEquals(new Reference('.messenger.transport.customised.signing_serializer'), $transportArguments[2]);
        $this->assertEquals(new Reference('messenger.transport.native_php_serializer'), $container->getDefinition('.messenger.transport.customised.signing_serializer')->getArgument(0));

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

        $syncFactoryArguments = $container->getDefinition('messenger.transport.sync.factory')->getArguments();
        $this->assertEquals(new Reference('messenger.routable_message_bus'), $syncFactoryArguments[0]);
        $this->assertEquals(new Reference('messenger.retry_strategy_locator'), $syncFactoryArguments[1]);
        $this->assertEquals($failureTransportsByTransportNameServiceLocator, $syncFactoryArguments[2]);
        $this->assertEquals(new Reference('event_dispatcher'), $syncFactoryArguments[3]);
        $this->assertEquals(new Reference('logger', ContainerInterface::IGNORE_ON_INVALID_REFERENCE), $syncFactoryArguments[4]);

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
        $this->assertSame('.messenger.transport.async.signing_serializer', (string) $serializer->getArgument(0));
        $this->assertSame('app.claim_check_pool', (string) $serializer->getArgument(1));
        $this->assertSame(200000, $serializer->getArgument(2));

        $this->assertSame('messenger.default_serializer', (string) $container->getDefinition('.messenger.transport.async.signing_serializer')->getArgument(0));

        $serializers = $container->getDefinition('messenger.transport.serializer_locator')->getArgument(0);
        $this->assertSame('.messenger.transport.async.claim_check_serializer', (string) $serializers['async']);
    }

    public function testMessengerTransportsThatSignEveryMessage()
    {
        $container = $this->createContainerFromFile('messenger_sign');

        foreach (['async' => ['messenger.default_serializer', true, false], 'failed' => ['messenger.transport.symfony_serializer', true, true], 'plain' => ['messenger.default_serializer', false, false]] as $name => [$innerId, $signAll, $acceptUnverified]) {
            $serializer = $container->getDefinition('.messenger.transport.'.$name.'.signing_serializer');
            $this->assertSame(SigningSerializer::class, $serializer->getClass());
            $this->assertSame($innerId, (string) $serializer->getArgument(0));
            $this->assertSame($signAll, ['*'] === $serializer->getArgument(2));
            $this->assertSame($acceptUnverified, $serializer->getArgument(4));
        }

        $serializers = $container->getDefinition('messenger.transport.serializer_locator')->getArgument(0);
        $this->assertSame('.messenger.transport.async.claim_check_serializer', (string) $serializers['async']);
        $this->assertSame('.messenger.transport.async.signing_serializer', (string) $container->getDefinition('.messenger.transport.async.claim_check_serializer')->getArgument(0));
        $this->assertSame('.messenger.transport.failed.signing_serializer', (string) $serializers['failed']);
        $this->assertSame('.messenger.transport.failed.signing_serializer', (string) $container->getDefinition('messenger.transport.failed')->getArgument(2));
        $this->assertSame('.messenger.transport.plain.signing_serializer', (string) $serializers['plain']);
        $this->assertSame('.messenger.transport.plain.signing_serializer', (string) $container->getDefinition('messenger.transport.plain')->getArgument(2));
    }

    #[DataProvider('provideFailureTransportsThatSignEveryMessage')]
    public function testMessengerFailureTransportThatSignsEveryMessageAcceptsTheUnverifiedMessagesOfTheTransportsItServes(array $config)
    {
        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) use ($config) {
            $container->loadFromExtension('messenger', $config);
        });

        $failed = $container->getDefinition('.messenger.transport.failed.signing_serializer');
        $this->assertSame(['*'], $failed->getArgument(2));
        $this->assertTrue($failed->getArgument(4));
        $this->assertFalse($container->getDefinition('.messenger.transport.plain.signing_serializer')->getArgument(4));
    }

    public static function provideFailureTransportsThatSignEveryMessage(): iterable
    {
        yield 'global' => [[
            'failure_transport' => 'failed',
            'transports' => [
                'async' => ['dsn' => 'in-memory:///', 'sign' => true],
                'plain' => 'in-memory:///',
                'failed' => ['dsn' => 'in-memory:///', 'sign' => true],
            ],
        ]];

        yield 'per transport' => [[
            'transports' => [
                'plain' => ['dsn' => 'in-memory:///', 'failure_transport' => 'failed'],
                'failed' => ['dsn' => 'in-memory:///', 'sign' => true],
            ],
        ]];
    }

    public function testMessengerTransportsUsedAsFailureTransportsAcceptUnverifiedMessages()
    {
        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('messenger', [
                'failure_transport' => 'failed',
                'transports' => [
                    'async' => ['dsn' => 'in-memory:///', 'failure_transport' => 'failed_async'],
                    'plain' => 'in-memory:///',
                    'failed' => 'in-memory:///',
                    'failed_async' => 'in-memory:///',
                ],
            ]);
        });

        foreach (['async' => false, 'plain' => false, 'failed' => true, 'failed_async' => true] as $name => $acceptUnverified) {
            $this->assertSame($acceptUnverified, $container->getDefinition('.messenger.transport.'.$name.'.signing_serializer')->getArgument(4));
        }
    }

    #[DataProvider('provideTransportsThatSignEveryMessageWithAFailureTransportThatDoesNot')]
    public function testMessengerTransportThatSignsEveryMessageNeedsAFailureTransportThatDoesToo(array $config)
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Invalid Messenger configuration: the "async" transport signs every message, so its failure transport "failed" must sign every message too.');

        $this->createContainerFromClosure(static function (ContainerBuilder $container) use ($config) {
            $container->loadFromExtension('messenger', $config);
        });
    }

    public static function provideTransportsThatSignEveryMessageWithAFailureTransportThatDoesNot(): iterable
    {
        yield 'global' => [[
            'failure_transport' => 'failed',
            'transports' => [
                'async' => ['dsn' => 'in-memory:///', 'sign' => true],
                'failed' => 'in-memory:///',
            ],
        ]];

        yield 'per transport' => [[
            'transports' => [
                'async' => ['dsn' => 'in-memory:///', 'sign' => true, 'failure_transport' => 'failed'],
                'failed' => 'in-memory:///',
            ],
        ]];
    }

    public function testMessengerTransportThatSignsEveryMessageCanHaveAFailureTransportOfItsOwn()
    {
        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('messenger', [
                'failure_transport' => 'failed',
                'transports' => [
                    'async' => ['dsn' => 'in-memory:///', 'sign' => true, 'failure_transport' => 'failed_signed'],
                    'plain' => 'in-memory:///',
                    'failed' => 'in-memory:///',
                    'failed_signed' => ['dsn' => 'in-memory:///', 'sign' => true, 'failure_transport' => 'failed_signed'],
                ],
            ]);
        });

        $this->assertSame(['*'], $container->getDefinition('.messenger.transport.failed_signed.signing_serializer')->getArgument(2));
    }

    public function testMessengerOutboxOfATransportThatSignsEveryMessageMustSignToo()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Invalid Messenger configuration: the "orders" transport signs every message, so its outbox "outbox" must sign every message too.');

        $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('messenger', [
                'transports' => [
                    'orders' => ['dsn' => 'in-memory:///', 'sign' => true, 'outbox' => 'outbox'],
                    'outbox' => 'in-memory:///',
                ],
            ]);
        });
    }

    public function testMessengerOutboxThatSignsEveryMessageCanServeATransportThatDoesNot()
    {
        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('messenger', [
                'transports' => [
                    'orders' => ['dsn' => 'in-memory:///', 'outbox' => 'outbox'],
                    'outbox' => ['dsn' => 'in-memory:///', 'sign' => true],
                ],
            ]);
        });

        $this->assertTrue($container->hasDefinition('.messenger.transport.orders.outbox_sender'));
    }

    public function testMessengerOutbox()
    {
        $container = $this->createContainerFromFile('messenger_outbox');

        $outboxSender = $container->getDefinition('.messenger.transport.orders.outbox_sender');
        $this->assertSame(OutboxSender::class, $outboxSender->getClass());
        $this->assertEquals([new Reference('messenger.transport.orders'), new Reference('messenger.transport.outbox'), 'orders'], $outboxSender->getArguments());

        $sendersLocatorId = (string) $container->getDefinition('messenger.senders_locator')->getArgument(1);
        $senders = $container->getDefinition($sendersLocatorId)->getArgument(0);
        $this->assertEquals(new Reference('.messenger.transport.orders.outbox_sender'), $senders['orders']->getValues()[0]);
        $this->assertEquals(new Reference('.messenger.transport.orders.outbox_sender'), $senders['messenger.transport.orders']->getValues()[0]);
        $this->assertEquals(new Reference('messenger.transport.outbox'), $senders['outbox']->getValues()[0]);
        $this->assertSame($sendersLocatorId, (string) $container->getDefinition('messenger.retry.send_failed_message_for_retry_listener')->getArgument(0));

        $transport = $container->getDefinition('messenger.transport.orders');
        $this->assertEquals([new Reference('messenger.transport_factory'), 'createTransport'], $transport->getFactory());
        $this->assertSame('amqp://localhost/%2f/orders', $transport->getArgument(0));
        $this->assertSame(['transport_name' => 'orders'], $transport->getArgument(1));
        $this->assertEquals([['alias' => 'orders', 'is_failure_transport' => false, 'priority' => 0]], $transport->getTag('messenger.receiver'));
    }

    public function testMessengerOutboxMustBeAConfiguredTransport()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Invalid Messenger configuration: the outbox "missing" of the "orders" transport is not a configured transport.');

        $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('messenger', [
                'transports' => [
                    'orders' => ['dsn' => 'in-memory:///', 'outbox' => 'missing'],
                ],
            ]);
        });
    }

    public function testMessengerOutboxCannotBeTheTransportItself()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Invalid Messenger configuration: the "orders" transport cannot be its own outbox.');

        $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('messenger', [
                'transports' => [
                    'orders' => ['dsn' => 'in-memory:///', 'outbox' => 'orders'],
                ],
            ]);
        });
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
        $this->assertEquals(new Reference('serializer'), $serializerTransportDefinition->getArgument(0));
        $this->assertSame('csv', $serializerTransportDefinition->getArgument(1));
        $this->assertSame(['enable_max_depth' => true], $serializerTransportDefinition->getArgument(2));
    }

    public function testMessengerInteropSerializer()
    {
        $container = $this->createContainerFromFile('messenger_transport');

        $definition = $container->getDefinition('messenger.transport.interop_serializer');
        $this->assertSame(InteropSerializer::class, $definition->getClass());
        $this->assertEquals([new Reference('messenger.transport.symfony_serializer')], $definition->getArguments());

        $this->assertFalse($this->createContainerFromFile('messenger_multiple_buses')->hasDefinition('messenger.transport.interop_serializer'));
    }

    public function testMessengerTransportSerializerConfiguration()
    {
        $container = $this->createContainerFromFile('messenger_transport_serializer');

        $serializerTransportDefinition = $container->getDefinition('messenger.transport.symfony_serializer');
        $this->assertEquals(new Reference('serializer.api'), $serializerTransportDefinition->getArgument(0));
        $this->assertSame('json', $serializerTransportDefinition->getArgument(1));
        $this->assertSame([], $serializerTransportDefinition->getArgument(2));
    }

    public function testDeduplicationUsesTheDefaultLockFactory()
    {
        $container = $this->createContainerFromFile('messenger');

        foreach (self::DEDUPLICATION_SERVICES as $id) {
            $definition = $container->getDefinition($id);
            $this->assertEquals(new Reference('lock.factory'), $definition->getArgument(0), $id);
            $this->assertTrue($definition->hasTag('container.remove_if_missing'), $id);
        }
    }

    public function testDeduplicationUsesTheConfiguredLockFactory()
    {
        $container = $this->createContainerFromFile('messenger_deduplication_lock_factory');

        foreach (self::DEDUPLICATION_SERVICES as $id) {
            $definition = $container->getDefinition($id);
            $this->assertEquals(new Reference('lock.dedup.factory'), $definition->getArgument(0), $id);
            $this->assertFalse($definition->hasTag('container.remove_if_missing'), $id);
        }
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
            ['id' => 'decode_failed_message_middleware'],
            ['id' => 'flow_context'],
            ['id' => 'dispatch_on_failure'],
            ['id' => 'dispatch_after_current_bus'],
            ['id' => 'failed_message_processing_middleware'],
            ['id' => 'deduplicate_middleware'],
            ['id' => 'send_message', 'arguments' => [true]],
            ['id' => 'chain'],
            ['id' => 'handle_message', 'arguments' => ['index_1' => false]],
        ], $container->getParameter('messenger.bus.events.middleware'));
    }

    public function testMessengerBusMessages()
    {
        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('messenger', [
                'default_bus' => 'command.bus',
                'buses' => [
                    'command.bus' => ['messages' => DummyMessage::class],
                    'event.bus' => [],
                ],
            ]);
        });

        $this->assertSame([DummyMessage::class], $container->getDefinition('command.bus')->getArgument('$messageTypes'));
        $this->assertSame([[]], $container->getDefinition('event.bus')->getArguments());
    }

    public function testMessengerBusMessagesMustExist()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Invalid Messenger configuration: the class or interface "App\\Missing" listed in the "messages" of the "command.bus" bus does not exist.');

        $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('messenger', ['buses' => ['command.bus' => ['messages' => 'App\\Missing']]]);
        });
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
            ['id' => 'decode_failed_message_middleware'],
            ['id' => 'flow_context'],
            ['id' => 'dispatch_on_failure'],
            ['id' => 'dispatch_after_current_bus'],
            ['id' => 'failed_message_processing_middleware'],
            ['id' => 'deduplicate_middleware'],
            ['id' => 'send_message', 'arguments' => [true]],
            ['id' => 'chain'],
            ['id' => 'handle_message', 'arguments' => ['index_1' => false]],
        ], $container->getParameter('messenger.bus.commands.middleware'));
        $this->assertTrue($container->has('messenger.bus.events'));
        $this->assertSame([], $container->getDefinition('messenger.bus.events')->getArgument(0));
        $this->assertEquals([
            ['id' => 'add_default_stamps_middleware'],
            ['id' => 'add_bus_name_stamp_middleware', 'arguments' => ['messenger.bus.events']],
            ['id' => 'reject_redelivered_message_middleware'],
            ['id' => 'decode_failed_message_middleware'],
            ['id' => 'flow_context'],
            ['id' => 'dispatch_on_failure'],
            ['id' => 'dispatch_after_current_bus'],
            ['id' => 'failed_message_processing_middleware'],
            ['id' => 'deduplicate_middleware'],
            ['id' => 'with_factory', 'arguments' => ['foo', true, ['bar' => 'baz']]],
            ['id' => 'send_message', 'arguments' => [true]],
            ['id' => 'chain'],
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

        $this->assertSame('.messenger.transport.async.signing_serializer', (string) $container->getDefinition('messenger.transport.async')->getArgument(2));
        $this->assertSame('messenger.default_serializer', (string) $container->getDefinition('.messenger.transport.async.signing_serializer')->getArgument(0));

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

        $this->assertSame('.messenger.transport.async.signing_serializer', (string) $container->getDefinition('messenger.transport.async')->getArgument(2));
        $this->assertSame('messenger.default_serializer', (string) $container->getDefinition('.messenger.transport.async.signing_serializer')->getArgument(0));

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

        $this->assertSame('.messenger.transport.async.signing_serializer', (string) $container->getDefinition('messenger.transport.async')->getArgument(2));
        $this->assertSame('messenger.default_serializer', (string) $container->getDefinition('.messenger.transport.async.signing_serializer')->getArgument(0));
    }

    public function testMessengerRedispatchMessageRequiresSignature()
    {
        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('messenger', ['transports' => ['async' => ['dsn' => 'in-memory://']]]);
            $container->addCompilerPass(new MessengerPass());
        });

        $this->assertTrue($container->hasDefinition('messenger.signing_serializer'));
        $this->assertContains(RedispatchMessage::class, $container->getDefinition('messenger.signing_serializer')->getArgument(2));
    }

    public function testMessengerSigningSecretDefaultsToTheKernelSecret()
    {
        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('messenger', []);
        });

        $this->assertInstanceOf(Definition::class, $container->getDefinition('messenger.signing_serializer')->getArgument(1));
        $this->assertEquals([[new Parameter('kernel.secret')]], $container->getDefinition('.messenger.signing_serializer.signing_key')->getArguments());
    }

    public function testMessengerSigningSecrets()
    {
        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('messenger', [
                'serializer' => ['signing_secret' => ['new', 'old']],
                'transports' => ['async' => ['dsn' => 'in-memory://', 'sign' => true]],
            ]);
        });

        $this->assertSame(['new', 'old'], $container->getDefinition('.messenger.transport.async.signing_serializer')->getArgument(1));
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
