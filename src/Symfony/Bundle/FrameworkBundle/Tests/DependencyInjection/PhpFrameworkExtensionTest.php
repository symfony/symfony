<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\OutOfBoundsException;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Mailer\Bridge\Brevo\Webhook\BrevoRequestParser;
use Symfony\Component\Mailer\Bridge\Postmark\Webhook\PostmarkRequestParser;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\RateLimiter\CompoundRateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimiterBuilder;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Webhook\Client\AbstractRequestParser;
use Symfony\Component\Workflow\Definition;
use Symfony\Component\Workflow\DependencyInjection\WorkflowValidatorPass;
use Symfony\Component\Workflow\Exception\InvalidDefinitionException;
use Symfony\Component\Workflow\Validator\DefinitionValidatorInterface;

class PhpFrameworkExtensionTest extends FrameworkExtensionTestCase
{
    protected function loadFromFile(ContainerBuilder $container, $file)
    {
        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/Fixtures/php'));
        $loader->load($file.'.php');
    }

    public function testAssetsCannotHavePathAndUrl()
    {
        $this->expectException(\LogicException::class);
        $this->createContainerFromClosure(static function ($container) {
            $container->loadFromExtension('framework', [
                'assets' => [
                    'base_urls' => 'http://cdn.example.com',
                    'base_path' => '/foo',
                ],
            ]);
        });
    }

    public function testAssetPackageCannotHavePathAndUrl()
    {
        $this->expectException(\LogicException::class);
        $this->createContainerFromClosure(static function ($container) {
            $container->loadFromExtension('framework', [
                'assets' => [
                    'packages' => [
                        'impossible' => [
                            'base_urls' => 'http://cdn.example.com',
                            'base_path' => '/foo',
                        ],
                    ],
                ],
            ]);
        });
    }

    public function testWorkflowValidationStateMachine()
    {
        $this->expectException(InvalidDefinitionException::class);
        $this->expectExceptionMessage('A transition from a place/state must have an unique name. Multiple transitions named "a_to_b" from place/state "a" were found on StateMachine "article".');
        $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('framework', [
                'workflows' => [
                    'article' => [
                        'type' => 'state_machine',
                        'supports' => [
                            __CLASS__,
                        ],
                        'places' => [
                            'a',
                            'b',
                            'c',
                        ],
                        'transitions' => [
                            'a_to_b' => [
                                'from' => ['a'],
                                'to' => ['b', 'c'],
                            ],
                        ],
                    ],
                ],
            ]);
            $container->addCompilerPass(new WorkflowValidatorPass());
        });
    }

    #[DataProvider('provideWorkflowValidationCustomTests')]
    public function testWorkflowValidationCustomBroken(string $class, string $message)
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);
        $this->createContainerFromClosure(static function ($container) use ($class) {
            $container->loadFromExtension('framework', [
                'workflows' => [
                    'article' => [
                        'type' => 'state_machine',
                        'supports' => [
                            __CLASS__,
                        ],
                        'places' => [
                            'a',
                            'b',
                        ],
                        'transitions' => [
                            'a_to_b' => [
                                'from' => ['a'],
                                'to' => ['b'],
                            ],
                        ],
                        'definition_validators' => [
                            $class,
                        ],
                    ],
                ],
            ]);
        });
    }

    public static function provideWorkflowValidationCustomTests()
    {
        yield ['classDoesNotExist', 'Invalid configuration for path "framework.workflows.workflows.article.definition_validators.0": The validation class "classDoesNotExist" does not exist.'];

        yield [\DateTime::class, 'Invalid configuration for path "framework.workflows.workflows.article.definition_validators.0": The validation class "DateTime" is not an instance of "Symfony\Component\Workflow\Validator\DefinitionValidatorInterface".'];

        yield [WorkflowValidatorWithConstructor::class, 'Invalid configuration for path "framework.workflows.workflows.article.definition_validators.0": The "Symfony\\\\Bundle\\\\FrameworkBundle\\\\Tests\\\\DependencyInjection\\\\WorkflowValidatorWithConstructor" validation class constructor must not have any arguments.'];
    }

    public function testWorkflowDefaultMarkingStoreDefinition()
    {
        $container = $this->createContainerFromClosure(static function ($container) {
            $container->loadFromExtension('framework', [
                'workflows' => [
                    'workflow_a' => [
                        'type' => 'state_machine',
                        'marking_store' => [
                            'type' => 'method',
                            'property' => 'status',
                        ],
                        'supports' => [
                            __CLASS__,
                        ],
                        'places' => [
                            'a',
                            'b',
                        ],
                        'transitions' => [
                            'a_to_b' => [
                                'from' => ['a'],
                                'to' => ['b'],
                            ],
                        ],
                    ],
                    'workflow_b' => [
                        'type' => 'state_machine',
                        'supports' => [
                            __CLASS__,
                        ],
                        'places' => [
                            'a',
                            'b',
                        ],
                        'transitions' => [
                            'a_to_b' => [
                                'from' => ['a'],
                                'to' => ['b'],
                            ],
                        ],
                    ],
                ],
            ]);
        });

        $workflowA = $container->getDefinition('state_machine.workflow_a');
        $argumentsA = $workflowA->getArguments();
        $this->assertArrayHasKey('index_1', $argumentsA, 'workflow_a has a marking_store argument');
        $this->assertNotNull($argumentsA['index_1'], 'workflow_a marking_store argument is not null');

        $workflowB = $container->getDefinition('state_machine.workflow_b');
        $argumentsB = $workflowB->getArguments();
        $this->assertArrayHasKey('index_1', $argumentsB, 'workflow_b has a marking_store argument');
        $this->assertNull($argumentsB['index_1'], 'workflow_b marking_store argument is null');
    }

    public function testRateLimiterLockFactoryWithLockDisabled()
    {
        try {
            $this->createContainerFromClosure(static function (ContainerBuilder $container) {
                $container->loadFromExtension('framework', [
                    'lock' => false,
                    'rate_limiter' => [
                        'with_lock' => ['policy' => 'fixed_window', 'limit' => 10, 'interval' => '1 hour', 'lock_factory' => 'lock.factory'],
                    ],
                ]);
            });

            $this->fail('No LogicException thrown');
        } catch (LogicException $e) {
            $this->assertEquals('Rate limiter "with_lock" requires the Lock component to be configured.', $e->getMessage());
        }
    }

    public function testRateLimiterAutoLockFactoryWithLockEnabled()
    {
        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('framework', [
                'lock' => true,
                'rate_limiter' => [
                    'with_lock' => ['policy' => 'fixed_window', 'limit' => 10, 'interval' => '1 hour'],
                ],
            ]);
        });

        $withLock = $container->getDefinition('limiter.with_lock');
        $this->assertEquals('lock.factory', (string) $withLock->getArgument(2));
    }

    public function testRateLimiterAutoLockFactoryWithLockDisabled()
    {
        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('framework', [
                'lock' => false,
                'rate_limiter' => [
                    'without_lock' => ['policy' => 'fixed_window', 'limit' => 10, 'interval' => '1 hour'],
                ],
            ]);
        });

        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessageMatches('/^The argument "2" doesn\'t exist.*\.$/');

        $container->getDefinition('limiter.without_lock')->getArgument(2);
    }

    public function testRateLimiterDisableLockFactory()
    {
        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('framework', [
                'lock' => true,
                'rate_limiter' => [
                    'without_lock' => ['policy' => 'fixed_window', 'limit' => 10, 'interval' => '1 hour', 'lock_factory' => null],
                ],
            ]);
        });

        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessageMatches('/^The argument "2" doesn\'t exist.*\.$/');

        $container->getDefinition('limiter.without_lock')->getArgument(2);
    }

    public function testRateLimiterIsTagged()
    {
        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('framework', [
                'lock' => true,
                'rate_limiter' => [
                    'first' => ['policy' => 'fixed_window', 'limit' => 10, 'interval' => '1 hour'],
                    'second' => ['policy' => 'fixed_window', 'limit' => 10, 'interval' => '1 hour'],
                ],
            ]);
        });

        $this->assertSame('first', $container->getDefinition('limiter.first')->getTag('rate_limiter')[0]['name']);
        $this->assertSame('second', $container->getDefinition('limiter.second')->getTag('rate_limiter')[0]['name']);
    }

    public function testRateLimiterCompoundPolicy()
    {
        if (!class_exists(CompoundRateLimiterFactory::class)) {
            $this->markTestSkipped('CompoundRateLimiterFactory is not available.');
        }

        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('framework', [
                'lock' => true,
                'rate_limiter' => [
                    'first' => ['policy' => 'fixed_window', 'limit' => 10, 'interval' => '1 hour'],
                    'second' => ['policy' => 'sliding_window', 'limit' => 10, 'interval' => '1 hour'],
                    'compound' => ['policy' => 'compound', 'limiters' => ['first', 'second']],
                ],
            ]);
        });

        $this->assertSame([
            'policy' => 'fixed_window',
            'limit' => 10,
            'interval' => '1 hour',
            'anchor_at' => null,
            'id' => 'first',
        ], $container->getDefinition('limiter.first')->getArgument(0));
        $this->assertSame([
            'policy' => 'sliding_window',
            'limit' => 10,
            'interval' => '1 hour',
            'anchor_at' => null,
            'id' => 'second',
        ], $container->getDefinition('limiter.second')->getArgument(0));

        $definition = $container->getDefinition('limiter.compound');
        $this->assertSame(CompoundRateLimiterFactory::class, $definition->getClass());
        $this->assertEquals([
            'first' => new Reference('limiter.first'),
            'second' => new Reference('limiter.second'),
        ], $definition->getArgument(0)->getValues());
        $this->assertSame([], $definition->getArgument(1));
        $this->assertSame('limiter.compound', (string) $container->getAlias(RateLimiterFactoryInterface::class.' $compoundLimiter'));
    }

    public function testRateLimiterCompoundPolicyWithKeys()
    {
        if (!class_exists(CompoundRateLimiterFactory::class)) {
            $this->markTestSkipped('CompoundRateLimiterFactory is not available.');
        }

        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('framework', [
                'lock' => true,
                'rate_limiter' => [
                    'per_user' => ['policy' => 'fixed_window', 'limit' => 10, 'interval' => '1 hour'],
                    'global_quota' => ['policy' => 'fixed_window', 'limit' => 5000, 'interval' => '1 hour'],
                    'api' => [
                        'policy' => 'compound',
                        'limiters' => [
                            'per_user' => null,
                            'global_quota' => ['key' => 'global'],
                        ],
                    ],
                ],
            ]);
        });

        $definition = $container->getDefinition('limiter.api');
        $this->assertSame(CompoundRateLimiterFactory::class, $definition->getClass());
        $this->assertEquals([
            'per_user' => new Reference('limiter.per_user'),
            'global_quota' => new Reference('limiter.global_quota'),
        ], $definition->getArgument(0)->getValues());
        $this->assertSame(['global_quota' => 'global'], $definition->getArgument(1));
    }

    public function testRateLimiterCompoundPolicyWithSingleStringLimiter()
    {
        if (!class_exists(CompoundRateLimiterFactory::class)) {
            $this->markTestSkipped('CompoundRateLimiterFactory is not available.');
        }

        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('framework', [
                'rate_limiter' => [
                    'first' => ['policy' => 'fixed_window', 'limit' => 10, 'interval' => '1 hour'],
                    'compound' => ['policy' => 'compound', 'limiters' => 'first'],
                ],
            ]);
        });

        $this->assertEquals(
            ['first' => new Reference('limiter.first')],
            $container->getDefinition('limiter.compound')->getArgument(0)->getValues()
        );
    }

    public function testRateLimiterCompoundPolicyNoLimiters()
    {
        if (!class_exists(CompoundRateLimiterFactory::class)) {
            $this->markTestSkipped('CompoundRateLimiterFactory is not available.');
        }

        $this->expectException(\LogicException::class);
        $this->createContainerFromClosure(static function ($container) {
            $container->loadFromExtension('framework', [
                'rate_limiter' => [
                    'compound' => ['policy' => 'compound'],
                ],
            ]);
        });
    }

    public function testRateLimiterCompoundPolicyInvalidLimiters()
    {
        if (!class_exists(CompoundRateLimiterFactory::class)) {
            $this->markTestSkipped('CompoundRateLimiterFactory is not available.');
        }

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Compound rate limiter "compound" references unknown limiter(s) "invalid1", "invalid2".');
        $this->createContainerFromClosure(static function ($container) {
            $container->loadFromExtension('framework', [
                'rate_limiter' => [
                    'compound' => ['policy' => 'compound', 'limiters' => ['invalid1', 'invalid2']],
                ],
            ]);
        });
    }

    public function testRateLimiterAnchorAtRequiresMonthlyOrYearlyInterval()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The "anchor_at" option requires an "interval" of at least one month.');

        $this->createContainerFromClosure(static function ($container) {
            $container->loadFromExtension('framework', [
                'rate_limiter' => [
                    'sub_month' => [
                        'policy' => 'fixed_window',
                        'limit' => 10,
                        'interval' => '1 hour',
                        'anchor_at' => '2024-01-01 00:00:00 UTC',
                    ],
                ],
            ]);
        });
    }

    #[DataProvider('emailValidationModeProvider')]
    public function testValidatorEmailValidationMode(string $mode)
    {
        $this->expectNotToPerformAssertions();

        $this->createContainerFromClosure(static function (ContainerBuilder $container) use ($mode) {
            $container->loadFromExtension('framework', [
                'validation' => [
                    'email_validation_mode' => $mode,
                ],
            ]);
        });
    }

    public static function emailValidationModeProvider()
    {
        foreach (Email::VALIDATION_MODES as $mode) {
            yield [$mode];
        }
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testLegacyMessengerSigningSerializerWiring()
    {
        $this->expectUserDeprecationMessage('Since symfony/framework-bundle 8.1: Using the "senders" nesting level for messenger routing configuration is deprecated and will be removed in version 9.0. Use a flat list of senders instead.');

        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->register('signed_handler', 'stdClass')
                ->addTag('messenger.message_handler', ['handles' => DummyMessage::class, 'sign' => true]);

            $container->loadFromExtension('framework', [
                'messenger' => [
                    'transports' => [
                        'async' => ['dsn' => 'in-memory://'],
                    ],
                    'routing' => [
                        DummyMessage::class => ['senders' => ['async']],
                    ],
                    'buses' => [
                        'message_bus' => ['default_middleware' => ['enabled' => true]],
                    ],
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

            $container->loadFromExtension('framework', [
                'messenger' => [
                    'transports' => [
                        'async' => ['dsn' => 'in-memory://'],
                    ],
                    'routing' => [
                        DummyMessage::class => ['async'],
                    ],
                    'buses' => [
                        'message_bus' => ['default_middleware' => ['enabled' => true]],
                    ],
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

            $container->loadFromExtension('framework', [
                'handle_all_throwables' => true,
                'php_errors' => ['log' => true],
                'messenger' => [
                    'transports' => [
                        'async' => ['dsn' => 'in-memory://'],
                    ],
                    'routing' => [],
                    'buses' => [
                        'message_bus' => ['default_middleware' => ['enabled' => true]],
                    ],
                ],
            ]);
        });

        $this->assertTrue($container->hasDefinition('messenger.signing_serializer'));
        $mapping = $container->getDefinition('messenger.signing_serializer')->getArgument(2);
        $this->assertArrayHasKey('*', $mapping);
        $this->assertContains('messenger.default_serializer', $mapping['*']);
    }

    public function testMailerWebhookProdExcludesLocalhost()
    {
        if (!class_exists(AbstractRequestParser::class)) {
            // the bridges below extend it, loading them without it is fatal
            $this->markTestSkipped('The Webhook component is not installed.');
        }

        if (!\defined(BrevoRequestParser::class.'::PROVIDER_IPS') || !\defined(PostmarkRequestParser::class.'::PROVIDER_IPS')) {
            $this->markTestSkipped('PROVIDER_IPS not available on the installed bridges.');
        }

        $container = $this->createContainerFromClosure(static function ($container) {
            $container->loadFromExtension('framework', [
                'handle_all_throwables' => true,
                'php_errors' => ['log' => true],
                'mailer' => ['dsn' => 'smtp://null'],
                'webhook' => ['enabled' => true],
                'http_client' => ['enabled' => true],
                'serializer' => ['enabled' => true],
            ]);
        });

        foreach (['mailer.webhook.request_parser.brevo', 'mailer.webhook.request_parser.postmark'] as $service) {
            $this->assertArrayNotHasKey('$allowedIPs', $container->getDefinition($service)->getArguments());
        }
    }

    public function testMailerWebhookDebugAddsLocalhost()
    {
        if (!class_exists(AbstractRequestParser::class)) {
            // the bridges below extend it, loading them without it is fatal
            $this->markTestSkipped('The Webhook component is not installed.');
        }

        if (!\defined(BrevoRequestParser::class.'::PROVIDER_IPS') || !\defined(PostmarkRequestParser::class.'::PROVIDER_IPS')) {
            $this->markTestSkipped('PROVIDER_IPS not available on the installed bridges.');
        }

        $container = $this->createContainerFromClosure(static function ($container) {
            $container->loadFromExtension('framework', [
                'handle_all_throwables' => true,
                'php_errors' => ['log' => true],
                'mailer' => ['dsn' => 'smtp://null'],
                'webhook' => ['enabled' => true],
                'http_client' => ['enabled' => true],
                'serializer' => ['enabled' => true],
            ]);
        }, ['kernel.debug' => true]);

        $this->assertSame(
            [...BrevoRequestParser::PROVIDER_IPS, '127.0.0.1'],
            $container->getDefinition('mailer.webhook.request_parser.brevo')->getArgument('$allowedIPs')
        );
        $this->assertSame(
            [...PostmarkRequestParser::PROVIDER_IPS, '127.0.0.1'],
            $container->getDefinition('mailer.webhook.request_parser.postmark')->getArgument('$allowedIPs')
        );
    }

    public function testRateLimiterBuilderDefault()
    {
        if (!class_exists(RateLimiterBuilder::class)) {
            $this->markTestSkipped('RateLimiterBuilder is not available.');
        }

        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('framework', [
                'lock' => false,
                'rate_limiter' => true,
            ]);
        });

        $this->assertSame('cache.rate_limiter', (string) $container->getDefinition('limiter_builder.storage')->getArgument(0));

        $builder = $container->getDefinition('limiter_builder');
        $this->assertSame('limiter_builder.storage', (string) $builder->getArgument(0));
        $this->assertNull($builder->getArgument(1));

        $this->assertSame('limiter_builder', (string) $container->getAlias(RateLimiterBuilder::class));
    }

    public function testRateLimiterBuilderDefaultWithLock()
    {
        if (!class_exists(RateLimiterBuilder::class)) {
            $this->markTestSkipped('RateLimiterBuilder is not available.');
        }

        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('framework', [
                'lock' => true,
                'rate_limiter' => true,
            ]);
        });

        $builder = $container->getDefinition('limiter_builder');
        $this->assertSame('lock.factory', (string) $builder->getArgument(1));
    }

    public function testRateLimiterBuilderWithCustomCachePool()
    {
        if (!class_exists(RateLimiterBuilder::class)) {
            $this->markTestSkipped('RateLimiterBuilder is not available.');
        }

        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('framework', [
                'rate_limiter' => [
                    'builder' => ['cache_pool' => 'cache.app'],
                ],
            ]);
        });

        $this->assertSame('cache.app', (string) $container->getDefinition('limiter_builder.storage')->getArgument(0));
        $this->assertSame('limiter_builder.storage', (string) $container->getDefinition('limiter_builder')->getArgument(0));
    }

    public function testRateLimiterBuilderWithCustomStorageService()
    {
        if (!class_exists(RateLimiterBuilder::class)) {
            $this->markTestSkipped('RateLimiterBuilder is not available.');
        }

        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('framework', [
                'rate_limiter' => [
                    'builder' => ['storage_service' => 'my.storage'],
                ],
            ]);
        });

        $this->assertFalse($container->hasDefinition('limiter_builder.storage'));
        $this->assertSame('my.storage', (string) $container->getDefinition('limiter_builder')->getArgument(0));
    }

    public function testRateLimiterBuilderWithCustomLockFactory()
    {
        if (!class_exists(RateLimiterBuilder::class)) {
            $this->markTestSkipped('RateLimiterBuilder is not available.');
        }

        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('framework', [
                'lock' => true,
                'rate_limiter' => [
                    'builder' => ['lock_factory' => 'my.lock_factory'],
                ],
            ]);
        });

        $this->assertSame('my.lock_factory', (string) $container->getDefinition('limiter_builder')->getArgument(1));
    }

    public function testRateLimiterBuilderLockCanBeDisabledWhileLockIsEnabled()
    {
        if (!class_exists(RateLimiterBuilder::class)) {
            $this->markTestSkipped('RateLimiterBuilder is not available.');
        }

        $container = $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('framework', [
                'lock' => true,
                'rate_limiter' => [
                    'builder' => ['lock_factory' => null],
                ],
            ]);
        });

        $this->assertNull($container->getDefinition('limiter_builder')->getArgument(1));
    }

    public function testRateLimiterBuilderThrowsWhenLockIsNotConfigured()
    {
        if (!class_exists(RateLimiterBuilder::class)) {
            $this->markTestSkipped('RateLimiterBuilder is not available.');
        }

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Rate Limiter Builder requires the Lock component to be configured.');

        $this->createContainerFromClosure(static function (ContainerBuilder $container) {
            $container->loadFromExtension('framework', [
                'lock' => false,
                'rate_limiter' => [
                    'builder' => ['lock_factory' => 'lock.factory'],
                ],
            ]);
        });
    }
}

class WorkflowValidatorWithConstructor implements DefinitionValidatorInterface
{
    public function __construct(bool $enabled)
    {
    }

    public function validate(Definition $definition, string $name): void
    {
    }
}
