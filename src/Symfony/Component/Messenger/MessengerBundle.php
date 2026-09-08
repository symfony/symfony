<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Messenger;

use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\ConsoleBundle;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Kernel\AbstractBundle;
use Symfony\Component\DependencyInjection\Kernel\RequiredBundle;
use Symfony\Component\DependencyInjection\Kernel\ServicesBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\DependencyInjection\MessengerPass;
use Symfony\Component\Messenger\DependencyInjection\RemoveMissingDependenciesPass;
use Symfony\Component\Messenger\Handler\BatchHandlerInterface;
use Symfony\Component\Messenger\Transport\Sender\OutboxSender;
use Symfony\Component\Messenger\Transport\Serialization\ClaimCheckSerializer;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;
use Symfony\Component\Messenger\Transport\TransportFactoryInterface;
use Symfony\Component\Messenger\Transport\TransportInterface;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Provides the message buses, their middleware and the transports.
 */
#[RequiredBundle(ServicesBundle::class)]
#[RequiredBundle(ConsoleBundle::class, ignoreOnInvalid: true)]
class MessengerBundle extends AbstractBundle
{
    public function getPath(): string
    {
        return $this->path ??= __DIR__;
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new RemoveMissingDependenciesPass());
        // runs last so that the passes adjusting the middleware of each bus, which other bundles own, have run already
        $container->addCompilerPass(new MessengerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, -16);
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->canBeDisabled()
            ->validate()
                ->ifTrue(static fn ($v) => isset($v['buses']) && \count($v['buses']) > 1 && null === $v['default_bus'])
                ->thenInvalid('You must specify the "default_bus" if you define more than one bus.')
            ->end()
            ->validate()
                ->ifTrue(static fn ($v) => isset($v['buses']) && null !== $v['default_bus'] && !isset($v['buses'][$v['default_bus']]))
                ->then(static fn ($v) => throw new InvalidConfigurationException(\sprintf('The specified default bus "%s" is not configured. Available buses are "%s".', $v['default_bus'], implode('", "', array_keys($v['buses'])))))
            ->end()
            ->children()
                ->arrayNode('routing')
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('message_class')
                    ->beforeNormalization()
                        ->ifArray()
                        ->then(static function ($config) {
                            $newConfig = [];
                            foreach ($config as $k => $v) {
                                if (isset($v['senders'])) {
                                    trigger_deprecation('symfony/framework-bundle', '8.1', 'Using the "senders" nesting level for messenger routing configuration is deprecated and will be removed in version 9.0. Use a flat list of senders instead.');
                                }
                                $newConfig[$k] = $v['senders'] ?? (\is_array($v) ? array_values($v) : [$v]);
                            }

                            return $newConfig;
                        })
                    ->end()
                    ->arrayPrototype()
                        ->requiresAtLeastOneElement()
                        ->acceptAndWrap(['string'])
                        ->performNoDeepMerging()
                        ->scalarPrototype()->end()
                    ->end()
                ->end()
                ->arrayNode('serializer')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('default_serializer')
                            ->defaultValue('messenger.transport.native_php_serializer')
                            ->info('Service id to use as the default serializer for the transports.')
                        ->end()
                        ->arrayNode('symfony_serializer')
                            ->addDefaultsIfNotSet()
                            ->children()
                                ->scalarNode('format')->defaultValue('json')->info('Serialization format for the messenger.transport.symfony_serializer service (which is not the serializer used by default).')->end()
                                ->arrayNode('context')
                                    ->normalizeKeys(false)
                                    ->useAttributeAsKey('name')
                                    ->defaultValue([])
                                    ->info('Context array for the messenger.transport.symfony_serializer service (which is not the serializer used by default).')
                                    ->prototype('variable')->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('transports', 'transport')
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->acceptAndWrap(['string'], 'dsn')
                        ->children()
                            ->scalarNode('dsn')->end()
                            ->scalarNode('serializer')->defaultNull()->info('Service id of a custom serializer to use.')->end()
                            ->arrayNode('claim_check')
                                ->children()
                                    ->scalarNode('cache_pool')->isRequired()->cannotBeEmpty()->info('Service id of the dedicated cache pool used to store claims. Pools declared under "framework.cache.pools" must define a "default_lifetime".')->end()
                                    ->integerNode('max_size')->isRequired()->min(1)->info('Maximum encoded message size in bytes before using a claim check.')->end()
                                ->end()
                            ->end()
                            ->arrayNode('options', 'option')
                                ->useAttributeAsKey('key')
                                ->normalizeKeys(false)
                                ->defaultValue([])
                                ->prototype('variable')
                                ->end()
                            ->end()
                            ->scalarNode('failure_transport')
                                ->defaultNull()
                                ->info('Transport name to send failed messages to (after all retries have failed).')
                            ->end()
                            ->scalarNode('outbox')
                                ->defaultNull()
                                ->info('Name of the transport that stores the messages inside the current database transaction; consume that transport to forward them to this one.')
                            ->end()
                            ->arrayNode('retry_strategy')
                                ->addDefaultsIfNotSet()
                                ->acceptAndWrap(['string'], 'service')
                                ->beforeNormalization()
                                    ->ifArray()
                                    ->then(static function ($v) {
                                        if (isset($v['service']) && (isset($v['max_retries']) || isset($v['delay']) || isset($v['multiplier']) || isset($v['max_delay']))) {
                                            throw new \InvalidArgumentException('The "service" cannot be used along with the other "retry_strategy" options.');
                                        }

                                        return $v;
                                    })
                                ->end()
                                ->children()
                                    ->scalarNode('service')->defaultNull()->info('Service id to override the retry strategy entirely.')->end()
                                    ->integerNode('max_retries')->defaultValue(3)->min(0)->end()
                                    ->integerNode('delay')->defaultValue(1000)->min(0)->info('Time in ms to delay (or the initial value when multiplier is used).')->end()
                                    ->floatNode('multiplier')->defaultValue(2)->min(1)->info('If greater than 1, delay will grow exponentially for each retry: this delay = (delay * (multiple ^ retries)).')->end()
                                    ->integerNode('max_delay')->defaultValue(0)->min(0)->info('Max time in ms that a retry should ever be delayed (0 = infinite).')->end()
                                    ->floatNode('jitter')->defaultValue(0.1)->min(0)->max(1)->info('Randomness to apply to the delay (between 0 and 1).')->end()
                                ->end()
                            ->end()
                            ->scalarNode('rate_limiter')
                                ->defaultNull()
                                ->info('Rate limiter name to use when processing messages.')
                            ->end()
                            ->integerNode('priority')
                                ->defaultValue(0)
                                ->info('Order in which "messenger:consume --all" consumes this transport, higher comes first.')
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->scalarNode('failure_transport')
                    ->defaultNull()
                    ->info('Transport name to send failed messages to (after all retries have failed).')
                ->end()
                ->arrayNode('stop_worker_on_signals', 'stop_worker_on_signal')
                    ->defaultValue([])
                    ->info('A list of signals that should stop the worker; defaults to SIGTERM and SIGINT.')
                    ->acceptAndWrap(['int', 'string'])
                    ->beforeNormalization()
                        ->ifArray()
                        ->then(static function ($signals) {
                            return array_map(static function ($v) {
                                if (\is_string($v) && str_starts_with($v, 'SIG') && \array_key_exists($v, get_defined_constants(true)['pcntl'])) {
                                    return \constant($v);
                                }

                                if (!\is_int($v)) {
                                    throw new InvalidConfigurationException('The "stop_worker_on_signals" option must be an array of pcntl signals in messenger configuration.');
                                }

                                return $v;
                            }, $signals);
                        })
                    ->end()
                    ->scalarPrototype()->end()
                ->end()
                ->booleanNode('reject_redelivered_messages')
                    ->defaultTrue()
                    ->info('Whether redeliveries should be rejected and retried through a new message instead of being handled directly. This mostly makes sense for AMQP, which redelivers messages that were neither acknowledged nor rejected. Disabling it avoids losing a message when the retry or the failure transport is unreachable, at the risk of a redelivery loop that blocks the queue.')
                ->end()
                ->scalarNode('default_bus')->defaultNull()->end()
                ->arrayNode('buses', 'bus')
                    ->defaultValue(['messenger.bus.default' => ['default_middleware' => ['enabled' => true, 'allow_no_handlers' => false, 'allow_no_senders' => true], 'middleware' => []]])
                    ->normalizeKeys(false)
                    ->useAttributeAsKey('name')
                    ->arrayPrototype()
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->arrayNode('default_middleware')
                                ->beforeNormalization()
                                    ->ifString()
                                    ->then(static fn ($v) => [
                                        'enabled' => 'allow_no_handlers' === $v,
                                        'allow_no_handlers' => 'allow_no_handlers' === $v,
                                    ])
                                ->end()
                                ->beforeNormalization()->ifTrue()->then(static fn () => ['enabled' => true])->end()
                                ->beforeNormalization()->ifFalse()->then(static fn () => ['enabled' => false])->end()
                                ->canBeDisabled()
                                ->children()
                                    ->booleanNode('allow_no_handlers')->defaultFalse()->end()
                                    ->booleanNode('allow_no_senders')->defaultTrue()->end()
                                ->end()
                            ->end()
                            ->arrayNode('middleware')
                                ->performNoDeepMerging()
                                ->acceptAndWrap(['string'])
                                ->beforeNormalization()
                                    ->ifArray()
                                    ->then(static fn ($v) => \is_string(key($v)) ? [$v] : $v)
                                ->end()
                                ->defaultValue([])
                                ->arrayPrototype()
                                    ->acceptAndWrap(['string'], 'id')
                                    ->beforeNormalization()
                                        ->ifArray()
                                        ->then(static function ($middleware): array {
                                            if (isset($middleware['id'])) {
                                                return $middleware;
                                            }
                                            if (1 < \count($middleware)) {
                                                throw new \InvalidArgumentException('Invalid middleware at path "messenger": a map with a single factory id as key and its arguments as value was expected, '.json_encode($middleware).' given.');
                                            }

                                            return [
                                                'id' => key($middleware),
                                                'arguments' => current($middleware),
                                            ];
                                        })
                                    ->end()
                                    ->children()
                                        ->scalarNode('id')->isRequired()->cannotBeEmpty()->end()
                                        ->arrayNode('arguments', 'argument')
                                            ->normalizeKeys(false)
                                            ->defaultValue([])
                                            ->prototype('variable')
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        $container->registerForAutoconfiguration(BatchHandlerInterface::class)
            ->addTag('messenger.message_handler');
        $container->registerForAutoconfiguration(TransportFactoryInterface::class)
            ->addTag('messenger.transport_factory');

        $container->registerAttributeForAutoconfiguration(AsMessageHandler::class, static function (ChildDefinition $definition, AsMessageHandler $attribute, \ReflectionClass|\ReflectionMethod $reflector): void {
            $tagAttributes = get_object_vars($attribute);
            $tagAttributes['from_transport'] = $tagAttributes['fromTransport'];
            unset($tagAttributes['fromTransport']);
            if ($reflector instanceof \ReflectionMethod) {
                if (isset($tagAttributes['method'])) {
                    throw new LogicException(\sprintf('AsMessageHandler attribute cannot declare a method on "%s::%s()".', $reflector->class, $reflector->name));
                }
                $tagAttributes['method'] = $reflector->getName();
            }
            $definition->addTag('messenger.message_handler', $tagAttributes);
        });
        $container->registerAttributeForAutoconfiguration(AsMessage::class, static function (ChildDefinition $definition, AsMessage $attribute): void {
            $definition->addResourceTag('messenger.message', [
                'transport' => $attribute->transport,
                'serializedTypeName' => $attribute->serializedTypeName ?? null,
                'serializedTypeNameAliases' => $attribute->serializedTypeNameAliases ?? [],
            ]);
        });

        if (!$config['enabled']) {
            return;
        }

        $configurator->import('Resources/config/messenger.php');

        if ($container->getParameter('kernel.debug')) {
            $configurator->import('Resources/config/messenger_debug.php');
        }

        if ($hasConsole = class_exists(Application::class)) {
            $configurator->import('Resources/config/console.php');
        }

        if (!interface_exists(DenormalizerInterface::class)) {
            $container->removeDefinition('serializer.normalizer.flatten_exception');
        }

        if (ContainerBuilder::willBeAvailable('symfony/amqp-messenger', Bridge\Amqp\Transport\AmqpTransportFactory::class, ['symfony/messenger'])) {
            $container->getDefinition('messenger.transport.amqp.factory')->addTag('messenger.transport_factory');
        }

        if (ContainerBuilder::willBeAvailable('symfony/amp-sql-messenger', Bridge\AmpSql\Transport\AmpSqlTransportFactory::class, ['symfony/messenger'])) {
            $container->getDefinition('messenger.transport.amp_sql.factory')->addTag('messenger.transport_factory');
        }

        if (ContainerBuilder::willBeAvailable('symfony/redis-messenger', Bridge\Redis\Transport\RedisTransportFactory::class, ['symfony/messenger'])) {
            $container->getDefinition('messenger.transport.redis.factory')->addTag('messenger.transport_factory');
        }

        if (ContainerBuilder::willBeAvailable('symfony/amazon-sqs-messenger', Bridge\AmazonSqs\Transport\AmazonSqsTransportFactory::class, ['symfony/messenger'])) {
            $container->getDefinition('messenger.transport.sqs.factory')->addTag('messenger.transport_factory');
        }

        if (ContainerBuilder::willBeAvailable('symfony/beanstalkd-messenger', Bridge\Beanstalkd\Transport\BeanstalkdTransportFactory::class, ['symfony/messenger'])) {
            $container->getDefinition('messenger.transport.beanstalkd.factory')->addTag('messenger.transport_factory');
        }

        if (ContainerBuilder::willBeAvailable('symfony/mongodb-messenger', Bridge\MongoDb\Transport\MongoDbTransportFactory::class, ['symfony/messenger'])) {
            $container->getDefinition('messenger.transport.mongodb.factory')->addTag('messenger.transport_factory');
        }

        if ($config['stop_worker_on_signals'] && $hasConsole) {
            $container->getDefinition('console.command.messenger_consume_messages')
                ->replaceArgument(8, $config['stop_worker_on_signals']);
            $container->getDefinition('console.command.messenger_failed_messages_retry')
                ->replaceArgument(6, $config['stop_worker_on_signals']);
        }

        if (null === $config['default_bus'] && 1 === \count($config['buses'])) {
            $config['default_bus'] = key($config['buses']);
        }

        $defaultMiddleware = [
            'before' => [
                ['id' => 'add_default_stamps_middleware'],
                ['id' => 'add_bus_name_stamp_middleware'],
                ...($config['reject_redelivered_messages'] ? [['id' => 'reject_redelivered_message_middleware']] : []),
                ['id' => 'dispatch_after_current_bus'],
                ['id' => 'decode_failed_message_middleware'],
                ['id' => 'failed_message_processing_middleware'],
            ],
            'after' => [
                ['id' => 'send_message'],
                ['id' => 'handle_message'],
            ],
        ];

        // RemoveMissingDependenciesPass drops the middleware again when no default lock factory is registered
        if (class_exists(LockFactory::class)) {
            $defaultMiddleware['before'][] = ['id' => 'deduplicate_middleware'];
        }

        foreach ($config['buses'] as $busId => $bus) {
            $middleware = $bus['middleware'];

            if ($bus['default_middleware']['enabled']) {
                $defaultMiddleware['after'][0]['arguments'] = [$bus['default_middleware']['allow_no_senders']];
                $defaultMiddleware['after'][1]['arguments'] = ['index_1' => $bus['default_middleware']['allow_no_handlers']];

                $middleware = array_merge($defaultMiddleware['before'], $middleware, $defaultMiddleware['after']);
            }

            foreach ($middleware as $key => $middlewareItem) {
                if (!interface_exists(ValidatorInterface::class) && \in_array($middlewareItem['id'], ['validation', 'messenger.middleware.validation'], true)) {
                    throw new LogicException('The Validation middleware is only available when the Validator component is installed. Try running "composer require symfony/validator".');
                }

                // argument to add_bus_name_stamp_middleware
                if ('add_bus_name_stamp_middleware' === $middlewareItem['id']) {
                    $middleware[$key]['arguments'] = [$busId];
                }

                if ('doctrine_open_transaction_logger' === $middlewareItem['id'] && isset($middleware[$key]['arguments'][0])) {
                    $middleware[$key]['arguments'] = ['$entityManagerName' => $middleware[$key]['arguments'][0]];
                }
            }

            // RemoveMissingDependenciesPass drops the middleware again when no stopwatch is registered
            if ($container->getParameter('kernel.debug') && class_exists(Stopwatch::class)) {
                array_unshift($middleware, ['id' => 'traceable', 'arguments' => [$busId]]);
            }

            $container->setParameter($busId.'.middleware', $middleware);
            $container->register($busId, MessageBus::class)->addArgument([])->addTag('messenger.bus');

            if ($busId === $config['default_bus']) {
                $container->setAlias('messenger.default_bus', $busId)->setPublic(true);
                $container->setAlias(MessageBusInterface::class, $busId);
            } else {
                $container->registerAliasForArgument($busId, MessageBusInterface::class);
            }
        }

        if (empty($config['transports'])) {
            $container->removeDefinition('messenger.transport.symfony_serializer');
            $container->removeDefinition('messenger.transport.amqp.factory');
            $container->removeDefinition('messenger.transport.redis.factory');
            $container->removeDefinition('messenger.transport.sqs.factory');
            $container->removeDefinition('messenger.transport.beanstalkd.factory');
            $container->removeDefinition('messenger.transport.mongodb.factory');
            $container->removeAlias(SerializerInterface::class);
        } else {
            $container->getDefinition('messenger.transport.symfony_serializer')
                ->replaceArgument(1, $config['serializer']['symfony_serializer']['format'])
                ->replaceArgument(2, $config['serializer']['symfony_serializer']['context']);
            $container->setAlias('messenger.default_serializer', $config['serializer']['default_serializer']);
        }

        $failureTransports = [];
        if ($config['failure_transport']) {
            if (!isset($config['transports'][$config['failure_transport']])) {
                throw new LogicException(\sprintf('Invalid Messenger configuration: the failure transport "%s" is not a valid transport or service id.', $config['failure_transport']));
            }

            $container->setAlias('messenger.failure_transports.default', 'messenger.transport.'.$config['failure_transport']);
            $failureTransports[] = $config['failure_transport'];
        }

        $failureTransportsByName = [];
        foreach ($config['transports'] as $name => $transport) {
            if ($transport['failure_transport']) {
                $failureTransports[] = $transport['failure_transport'];
                $failureTransportsByName[$name] = $transport['failure_transport'];
            } elseif ($config['failure_transport']) {
                $failureTransportsByName[$name] = $config['failure_transport'];
            }
        }

        $senderAliases = [];
        $transportRetryReferences = [];
        $transportRateLimiterReferences = [];
        $serializerReferencesByTransport = [];
        $serializerIds = [];
        foreach ($config['transports'] as $name => $transport) {
            $serializerId = $transport['serializer'] ?? 'messenger.default_serializer';
            $transportSerializerId = $serializerId;
            $tags = [
                'alias' => $name,
                'is_failure_transport' => \in_array($name, $failureTransports, true),
                'priority' => $transport['priority'],
            ];
            if ($transport['claim_check'] ?? null) {
                $container->setDefinition($transportSerializerId = '.messenger.transport.'.$name.'.claim_check_serializer', (new Definition(ClaimCheckSerializer::class))
                    ->setArguments([
                        new Reference($serializerId),
                        new Reference($transport['claim_check']['cache_pool']),
                        $transport['claim_check']['max_size'],
                    ]));
            }

            $serializerReferencesByTransport[$name] = new Reference($transportSerializerId);
            if (str_starts_with($transport['dsn'], 'sync://')) {
                $tags['is_consumable'] = false;
            }
            $transportDefinition = (new Definition(TransportInterface::class))
                ->setFactory([new Reference('messenger.transport_factory'), 'createTransport'])
                ->setArguments([$transport['dsn'], $transport['options'] + ['transport_name' => $name], new Reference($transportSerializerId)])
                ->addTag('messenger.receiver', $tags)
            ;
            $container->setDefinition($transportId = 'messenger.transport.'.$name, $transportDefinition);
            $senderAliases[$name] = $transportId;
            $serializerIds[$transportId] = $serializerId;

            if (null !== $transport['retry_strategy']['service']) {
                $transportRetryReferences[$name] = new Reference($transport['retry_strategy']['service']);
            } else {
                $retryServiceId = \sprintf('messenger.retry.multiplier_retry_strategy.%s', $name);
                $retryDefinition = new ChildDefinition('messenger.retry.abstract_multiplier_retry_strategy');
                $retryDefinition
                    ->replaceArgument(0, $transport['retry_strategy']['max_retries'])
                    ->replaceArgument(1, $transport['retry_strategy']['delay'])
                    ->replaceArgument(2, $transport['retry_strategy']['multiplier'])
                    ->replaceArgument(3, $transport['retry_strategy']['max_delay'])
                    ->replaceArgument(4, $transport['retry_strategy']['jitter']);
                $container->setDefinition($retryServiceId, $retryDefinition);

                $transportRetryReferences[$name] = new Reference($retryServiceId);
            }

            if ($transport['rate_limiter']) {
                if (!interface_exists(LimiterInterface::class)) {
                    throw new LogicException('Rate limiter cannot be used within Messenger as the RateLimiter component is not installed. Try running "composer require symfony/rate-limiter".');
                }

                $transportRateLimiterReferences[$name] = new Reference('limiter.'.$transport['rate_limiter']);
            }
        }

        $container->getDefinition('messenger.transport.serializer_locator')->replaceArgument(0, $serializerReferencesByTransport);

        $senderReferences = [];
        foreach ($senderAliases as $alias => $transportId) {
            $senderReferences[$alias] = new Reference($transportId);
        }
        foreach ($senderAliases as $transportId) {
            $senderReferences[$transportId] = new Reference($transportId);
        }

        foreach ($config['transports'] as $name => $transport) {
            if ($transport['failure_transport']) {
                if (!isset($senderReferences[$transport['failure_transport']])) {
                    throw new LogicException(\sprintf('Invalid Messenger configuration: the failure transport "%s" is not a valid transport or service id.', $transport['failure_transport']));
                }
            }

            if ($transport['outbox']) {
                if (!isset($config['transports'][$transport['outbox']])) {
                    throw new LogicException(\sprintf('Invalid Messenger configuration: the outbox "%s" of the "%s" transport is not a configured transport.', $transport['outbox'], $name));
                }
                if ($transport['outbox'] === $name) {
                    throw new LogicException(\sprintf('Invalid Messenger configuration: the "%s" transport cannot be its own outbox.', $name));
                }

                $container->setDefinition($outboxSenderId = '.messenger.transport.'.$name.'.outbox_sender', (new Definition(OutboxSender::class))
                    ->setArguments([new Reference($senderAliases[$name]), new Reference($senderAliases[$transport['outbox']]), $name]));
                $senderReferences[$name] = $senderReferences[$senderAliases[$name]] = new Reference($outboxSenderId);
            }
        }

        $failureTransportReferencesByTransportName = array_map(static fn ($failureTransportName) => $senderReferences[$failureTransportName], $failureTransportsByName);

        $messageToSendersMapping = [];
        foreach ($config['routing'] as $message => $messageSenders) {
            if ('*' !== $message && !class_exists($message) && !interface_exists($message, false) && !preg_match('/^(?:[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*+\\\\)++\*$/', $message)) {
                if (str_contains($message, '*')) {
                    throw new LogicException(\sprintf('Invalid Messenger routing configuration: invalid namespace "%s" wildcard.', $message));
                }

                throw new LogicException(\sprintf('Invalid Messenger routing configuration: class or interface "%s" not found.', $message));
            }

            // make sure senderAliases contains all senders
            foreach ($messageSenders as $sender) {
                if (!isset($senderReferences[$sender])) {
                    throw new LogicException(\sprintf('Invalid Messenger routing configuration: the "%s" class is being routed to a sender called "%s". This is not a valid transport or service id.', $message, $sender));
                }
            }

            $messageToSendersMapping[$message] = $messageSenders;
        }

        $sendersServiceLocator = ServiceLocatorTagPass::register($container, $senderReferences);

        $container->getDefinition('messenger.senders_locator')
            ->replaceArgument(0, $messageToSendersMapping)
            ->replaceArgument(1, $sendersServiceLocator)
        ;

        $messageToSerializersMapping = [];
        foreach ($messageToSendersMapping as $message => $senders) {
            foreach ($senders as $sender) {
                $serializerId = $serializerIds[$senderAliases[$sender] ?? $sender];
                $messageToSerializersMapping[$message][$serializerId] = $serializerId;
            }
            $messageToSerializersMapping[$message] = array_keys($messageToSerializersMapping[$message]);
        }

        // Transports can carry any message class regardless of routing, so every transport
        // serializer must be decoration-eligible whenever signing is requested.
        $messageToSerializersMapping['*'] = array_values(array_unique($serializerIds));

        $container->getDefinition('messenger.signing_serializer')
            ->replaceArgument(2, $messageToSerializersMapping);

        $container->getDefinition('messenger.retry.send_failed_message_for_retry_listener')
            ->replaceArgument(0, $sendersServiceLocator)
        ;

        $container->getDefinition('messenger.retry_strategy_locator')
            ->replaceArgument(0, $transportRetryReferences);

        if (!$transportRateLimiterReferences) {
            $container->removeDefinition('messenger.rate_limiter_locator');
        } else {
            $container->getDefinition('messenger.rate_limiter_locator')
                ->replaceArgument(0, $transportRateLimiterReferences);
        }

        if ($failureTransports) {
            if ($hasConsole) {
                $container->getDefinition('console.command.messenger_failed_messages_retry')
                    ->replaceArgument(0, $config['failure_transport']);
                $container->getDefinition('console.command.messenger_failed_messages_show')
                    ->replaceArgument(0, $config['failure_transport']);
                $container->getDefinition('console.command.messenger_failed_messages_remove')
                    ->replaceArgument(0, $config['failure_transport']);
            }

            $failureTransportsByTransportNameServiceLocator = ServiceLocatorTagPass::register($container, $failureTransportReferencesByTransportName);
            $container->getDefinition('messenger.failure.send_failed_message_to_failure_transport_listener')
                ->replaceArgument(0, $failureTransportsByTransportNameServiceLocator)
                ->replaceArgument(2, $failureTransportsByName);
        } else {
            $container->removeDefinition('messenger.failure.send_failed_message_to_failure_transport_listener');
            $container->removeDefinition('console.command.messenger_failed_messages_retry');
            $container->removeDefinition('console.command.messenger_failed_messages_show');
            $container->removeDefinition('console.command.messenger_failed_messages_remove');
        }
    }
}
