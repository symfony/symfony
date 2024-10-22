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

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\OutOfBoundsException;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\Workflow\Exception\InvalidDefinitionException;

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
                'annotations' => false,
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => ['log' => true],
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
                'annotations' => false,
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => ['log' => true],
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

    public function testWorkflowValidationPlacesIsArray()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The "places" option must be an array in workflow configuration.');
        $this->createContainerFromClosure(function ($container) {
            $container->loadFromExtension('framework', [
                'workflows' => [
                    'article' => [
                        'places' => null,
                    ],
                ],
            ]);
        });
    }

    public function testWorkflowValidationTransitonsIsArray()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The "transitions" option must be an array in workflow configuration.');
        $this->createContainerFromClosure(function ($container) {
            $container->loadFromExtension('framework', [
                'workflows' => [
                    'article' => [
                        'transitions' => null,
                    ],
                ],
            ]);
        });
    }

    public function testWorkflowValidationStateMachine()
    {
        $this->expectException(InvalidDefinitionException::class);
        $this->expectExceptionMessage('A transition from a place/state must have an unique name. Multiple transitions named "a_to_b" from place/state "a" were found on StateMachine "article".');
        $this->createContainerFromClosure(function ($container) {
            $container->loadFromExtension('framework', [
                'annotations' => false,
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => ['log' => true],
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
        });
    }

    /**
     * @dataProvider provideWorkflowValidationCustomTests
     */
    public function testWorkflowValidationCustomBroken(string $class, string $message)
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);
        $this->createContainerFromClosure(function ($container) use ($class) {
            $container->loadFromExtension('framework', [
                'annotations' => false,
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => ['log' => true],
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

        yield [\DateTime::class, 'Invalid configuration for path "framework.workflows.workflows.article.definition_validators.0": The validation class "DateTime" is not an instance of Symfony\Component\Workflow\Validator\DefinitionValidatorInterface.'];

        yield [WorkflowValidatorWithConstructor::class, 'Invalid configuration for path "framework.workflows.workflows.article.definition_validators.0": The validation class "Symfony\\\\Bundle\\\\FrameworkBundle\\\\Tests\\\\DependencyInjection\\\\WorkflowValidatorWithConstructor" constructor must not have any arguments.'];
    }

    public function testWorkflowDefaultMarkingStoreDefinition()
    {
        $container = $this->createContainerFromClosure(function ($container) {
            $container->loadFromExtension('framework', [
                'annotations' => false,
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => ['log' => true],
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

    public function testRateLimiterWithLockFactory()
    {
        try {
            $this->createContainerFromClosure(function (ContainerBuilder $container) {
                $container->loadFromExtension('framework', [
                    'annotations' => false,
                    'http_method_override' => false,
                    'handle_all_throwables' => true,
                    'php_errors' => ['log' => true],
                    'lock' => false,
                    'rate_limiter' => [
                        'with_lock' => ['policy' => 'fixed_window', 'limit' => 10, 'interval' => '1 hour'],
                    ],
                ]);
            });

            $this->fail('No LogicException thrown');
        } catch (LogicException $e) {
            $this->assertEquals('Rate limiter "with_lock" requires the Lock component to be configured.', $e->getMessage());
        }

        $container = $this->createContainerFromClosure(function (ContainerBuilder $container) {
            $container->loadFromExtension('framework', [
                'annotations' => false,
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => ['log' => true],
                'lock' => true,
                'rate_limiter' => [
                    'with_lock' => ['policy' => 'fixed_window', 'limit' => 10, 'interval' => '1 hour'],
                ],
            ]);
        });

        $withLock = $container->getDefinition('limiter.with_lock');
        $this->assertEquals('lock.factory', (string) $withLock->getArgument(2));
    }

    public function testRateLimiterLockFactory()
    {
        $container = $this->createContainerFromClosure(function (ContainerBuilder $container) {
            $container->loadFromExtension('framework', [
                'annotations' => false,
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => ['log' => true],
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
                'annotations' => false,
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => ['log' => true],
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
}

class WorkflowValidatorWithConstructor implements \Symfony\Component\Workflow\Validator\DefinitionValidatorInterface
{
    public function __construct(bool $enabled)
    {
    }

    public function validate(\Symfony\Component\Workflow\Definition $definition, string $name): void
    {
    }
}
