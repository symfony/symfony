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
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\OutOfBoundsException;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\Messenger\Tests\Fixtures\DummyMessage;
use Symfony\Component\RateLimiter\CompoundRateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Validator\Constraints\Email;
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
        $this->createContainerFromClosure(function ($container) {
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
        $this->createContainerFromClosure(function ($container) {
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
        $this->createContainerFromClosure(function (ContainerBuilder $container) {
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
        $this->createContainerFromClosure(function ($container) use ($class) {
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
        $container = $this->createContainerFromClosure(function ($container) {
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
            $this->createContainerFromClosure(function (ContainerBuilder $container) {
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
        $container = $this->createContainerFromClosure(function (ContainerBuilder $container) {
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
        $container = $this->createContainerFromClosure(function (ContainerBuilder $container) {
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
        $container = $this->createContainerFromClosure(function (ContainerBuilder $container) {
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
        $container = $this->createContainerFromClosure(function (ContainerBuilder $container) {
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

        $container = $this->createContainerFromClosure(function (ContainerBuilder $container) {
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
            'id' => 'first',
        ], $container->getDefinition('limiter.first')->getArgument(0));
        $this->assertSame([
            'policy' => 'sliding_window',
            'limit' => 10,
            'interval' => '1 hour',
            'id' => 'second',
        ], $container->getDefinition('limiter.second')->getArgument(0));

        $definition = $container->getDefinition('limiter.compound');
        $this->assertSame(CompoundRateLimiterFactory::class, $definition->getClass());
        $this->assertEquals(
            [
                'limiter.first',
                'limiter.second',
            ],
            $definition->getArgument(0)->getValues()
        );
        $this->assertSame('limiter.compound', (string) $container->getAlias(RateLimiterFactoryInterface::class.' $compoundLimiter'));
    }

    public function testRateLimiterCompoundPolicyNoLimiters()
    {
        if (!class_exists(CompoundRateLimiterFactory::class)) {
            $this->markTestSkipped('CompoundRateLimiterFactory is not available.');
        }

        $this->expectException(\LogicException::class);
        $this->createContainerFromClosure(function ($container) {
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
        $this->createContainerFromClosure(function ($container) {
            $container->loadFromExtension('framework', [
                'rate_limiter' => [
                    'compound' => ['policy' => 'compound', 'limiters' => ['invalid1', 'invalid2']],
                ],
            ]);
        });
    }

    #[DataProvider('emailValidationModeProvider')]
    public function testValidatorEmailValidationMode(string $mode)
    {
        $this->expectNotToPerformAssertions();

        $this->createContainerFromClosure(function (ContainerBuilder $container) use ($mode) {
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

    public function testMessengerSigningSerializerWiring()
    {
        $container = $this->createContainerFromClosure(function (ContainerBuilder $container) {
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
