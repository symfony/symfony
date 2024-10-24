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

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Configuration;
use Symfony\Bundle\FullStack;
use Symfony\Component\Cache\Adapter\DoctrineAdapter;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\FeatureFlag\FeatureChecker;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Lock\Store\SemaphoreStore;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Notifier\Notifier;
use Symfony\Component\RateLimiter\Policy\TokenBucketLimiter;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Scheduler\Messenger\SchedulerTransportFactory;
use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\TypeInfo\Type;
use Symfony\Component\Uid\Factory\UuidFactory;
use Symfony\Component\Webhook\Controller\WebhookController;

class ConfigurationTest extends TestCase
{
    public function testDefaultConfig()
    {
        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(true), [[
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'secret' => 's3cr3t',
            'serializer' => ['default_context' => ['foo' => 'bar']],
        ]]);

        $this->assertEquals(self::getBundleDefaultConfig(), $config);
    }

    public function getTestValidSessionName()
    {
        return [
            [null],
            ['PHPSESSID'],
            ['a&b'],
            [',_-!@#$%^*(){}:<>/?'],
        ];
    }

    /**
     * @dataProvider getTestInvalidSessionName
     */
    public function testInvalidSessionName($sessionName)
    {
        $processor = new Processor();

        $this->expectException(InvalidConfigurationException::class);

        $processor->processConfiguration(
            new Configuration(true),
            [[
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => ['log' => true],
                'session' => ['name' => $sessionName, 'cookie_secure' => 'auto', 'cookie_samesite' => 'lax'],
            ]]
        );
    }

    public static function getTestInvalidSessionName()
    {
        return [
            ['a.b'],
            ['a['],
            ['a[]'],
            ['a[b]'],
            ['a=b'],
            ['a+b'],
        ];
    }

    public function testAssetsCanBeEnabled()
    {
        $processor = new Processor();
        $configuration = new Configuration(true);
        $config = $processor->processConfiguration($configuration, [[
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'assets' => null,
        ]]);

        $defaultConfig = [
            'enabled' => true,
            'version_strategy' => null,
            'version' => null,
            'version_format' => '%%s?%%s',
            'base_path' => '',
            'base_urls' => [],
            'packages' => [],
            'json_manifest_path' => null,
            'strict_mode' => false,
        ];

        $this->assertEquals($defaultConfig, $config['assets']);
    }

    public function testAssetMapperCanBeEnabled()
    {
        $processor = new Processor();
        $configuration = new Configuration(true);
        $config = $processor->processConfiguration($configuration, [[
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'asset_mapper' => null,
        ]]);

        $defaultConfig = [
            'enabled' => true,
            'paths' => [],
            'excluded_patterns' => [],
            'server' => true,
            'public_prefix' => '/assets/',
            'missing_import_mode' => 'warn',
            'extensions' => [],
            'importmap_path' => '%kernel.project_dir%/importmap.php',
            'importmap_polyfill' => 'es-module-shims',
            'vendor_dir' => '%kernel.project_dir%/assets/vendor',
            'importmap_script_attributes' => [],
            'exclude_dotfiles' => true,
        ];

        $this->assertEquals($defaultConfig, $config['asset_mapper']);
    }

    /**
     * @dataProvider provideImportmapPolyfillTests
     */
    public function testAssetMapperPolyfillValue(mixed $polyfillValue, bool $isValid, mixed $expected)
    {
        $processor = new Processor();
        $configuration = new Configuration(true);

        if (!$isValid) {
            $this->expectException(InvalidConfigurationException::class);
            $this->expectExceptionMessage($expected);
        }

        $config = $processor->processConfiguration($configuration, [[
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'php_errors' => ['log' => true],
            'asset_mapper' => null === $polyfillValue ? [] : [
                'importmap_polyfill' => $polyfillValue,
            ],
        ]]);

        if ($isValid) {
            $this->assertEquals($expected, $config['asset_mapper']['importmap_polyfill']);
        }
    }

    public static function provideImportmapPolyfillTests()
    {
        yield [true, false, 'Must be either an importmap name or false.'];
        yield [null, true, 'es-module-shims'];
        yield ['es-module-shims', true, 'es-module-shims'];
        yield ['foo', true, 'foo'];
        yield [false, true, false];
    }

    /**
     * @dataProvider provideValidAssetsPackageNameConfigurationTests
     */
    public function testValidAssetsPackageNameConfiguration($packageName)
    {
        $processor = new Processor();
        $configuration = new Configuration(true);
        $config = $processor->processConfiguration($configuration, [
            [
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => ['log' => true],
                'assets' => [
                    'packages' => [
                        $packageName => [],
                    ],
                ],
            ],
        ]);

        $this->assertArrayHasKey($packageName, $config['assets']['packages']);
    }

    public static function provideValidAssetsPackageNameConfigurationTests(): array
    {
        return [
            ['foobar'],
            ['foo-bar'],
            ['foo_bar'],
        ];
    }

    /**
     * @dataProvider provideInvalidAssetConfigurationTests
     */
    public function testInvalidAssetsConfiguration(array $assetConfig, $expectedMessage)
    {
        $processor = new Processor();
        $configuration = new Configuration(true);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($expectedMessage);

        $processor->processConfiguration($configuration, [
            [
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => ['log' => true],
                'assets' => $assetConfig,
            ],
        ]);
    }

    public static function provideInvalidAssetConfigurationTests(): iterable
    {
        // helper to turn config into embedded package config
        $createPackageConfig = fn (array $packageConfig) => [
            'base_urls' => '//example.com',
            'version' => 1,
            'packages' => [
                'foo' => $packageConfig,
            ],
        ];

        $config = [
            'version' => 1,
            'version_strategy' => 'foo',
        ];
        yield [$config, 'You cannot use both "version_strategy" and "version" at the same time under "assets".'];
        yield [$createPackageConfig($config), 'You cannot use both "version_strategy" and "version" at the same time under "assets" packages.'];

        $config = [
            'json_manifest_path' => '/foo.json',
            'version_strategy' => 'foo',
        ];
        yield [$config, 'You cannot use both "version_strategy" and "json_manifest_path" at the same time under "assets".'];
        yield [$createPackageConfig($config), 'You cannot use both "version_strategy" and "json_manifest_path" at the same time under "assets" packages.'];

        $config = [
            'json_manifest_path' => '/foo.json',
            'version' => '1',
        ];
        yield [$config, 'You cannot use both "version" and "json_manifest_path" at the same time under "assets".'];
        yield [$createPackageConfig($config), 'You cannot use both "version" and "json_manifest_path" at the same time under "assets" packages.'];
    }

    /**
     * @dataProvider provideValidLockConfigurationTests
     */
    public function testValidLockConfiguration($lockConfig, $processedConfig)
    {
        $processor = new Processor();
        $configuration = new Configuration(true);
        $config = $processor->processConfiguration($configuration, [
            [
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => ['log' => true],
                'lock' => $lockConfig,
            ],
        ]);

        $this->assertArrayHasKey('lock', $config);

        $this->assertEquals($processedConfig, $config['lock']);
    }

    public static function provideValidLockConfigurationTests(): iterable
    {
        yield [null, ['enabled' => true, 'resources' => ['default' => [class_exists(SemaphoreStore::class) && SemaphoreStore::isSupported() ? 'semaphore' : 'flock']]]];

        yield ['flock', ['enabled' => true, 'resources' => ['default' => ['flock']]]];
        yield [['flock', 'semaphore'], ['enabled' => true, 'resources' => ['default' => ['flock', 'semaphore']]]];
        yield [['foo' => 'flock', 'bar' => 'semaphore'], ['enabled' => true, 'resources' => ['foo' => ['flock'], 'bar' => ['semaphore']]]];
        yield [['foo' => ['flock', 'semaphore'], 'bar' => 'semaphore'], ['enabled' => true, 'resources' => ['foo' => ['flock', 'semaphore'], 'bar' => ['semaphore']]]];
        yield [['default' => 'flock'], ['enabled' => true, 'resources' => ['default' => ['flock']]]];

        yield [['enabled' => false, 'flock'], ['enabled' => false, 'resources' => ['default' => ['flock']]]];
        yield [['enabled' => false, ['flock', 'semaphore']], ['enabled' => false, 'resources' => ['default' => ['flock', 'semaphore']]]];
        yield [['enabled' => false, 'foo' => 'flock', 'bar' => 'semaphore'], ['enabled' => false, 'resources' => ['foo' => ['flock'], 'bar' => ['semaphore']]]];
        yield [['enabled' => false, 'foo' => ['flock', 'semaphore']], ['enabled' => false, 'resources' => ['foo' => ['flock', 'semaphore']]]];
        yield [['enabled' => false, 'default' => 'flock'], ['enabled' => false, 'resources' => ['default' => ['flock']]]];

        yield [['resources' => 'flock'], ['enabled' => true, 'resources' => ['default' => ['flock']]]];
        yield [['resources' => ['flock', 'semaphore']], ['enabled' => true, 'resources' => ['default' => ['flock', 'semaphore']]]];
        yield [['resources' => ['foo' => 'flock', 'bar' => 'semaphore']], ['enabled' => true, 'resources' => ['foo' => ['flock'], 'bar' => ['semaphore']]]];
        yield [['resources' => ['foo' => ['flock', 'semaphore'], 'bar' => 'semaphore']], ['enabled' => true, 'resources' => ['foo' => ['flock', 'semaphore'], 'bar' => ['semaphore']]]];
        yield [['resources' => ['default' => 'flock']], ['enabled' => true, 'resources' => ['default' => ['flock']]]];

        yield [['enabled' => false, 'resources' => 'flock'], ['enabled' => false, 'resources' => ['default' => ['flock']]]];
        yield [['enabled' => false, 'resources' => ['flock', 'semaphore']], ['enabled' => false, 'resources' => ['default' => ['flock', 'semaphore']]]];
        yield [['enabled' => false, 'resources' => ['foo' => 'flock', 'bar' => 'semaphore']], ['enabled' => false, 'resources' => ['foo' => ['flock'], 'bar' => ['semaphore']]]];
        yield [['enabled' => false, 'resources' => ['foo' => ['flock', 'semaphore'], 'bar' => 'semaphore']], ['enabled' => false, 'resources' => ['foo' => ['flock', 'semaphore'], 'bar' => ['semaphore']]]];
        yield [['enabled' => false, 'resources' => ['default' => 'flock']], ['enabled' => false, 'resources' => ['default' => ['flock']]]];

        // xml

        yield [['resource' => ['flock']], ['enabled' => true, 'resources' => ['default' => ['flock']]]];
        yield [['resource' => ['flock', ['name' => 'foo', 'value' => 'semaphore']]], ['enabled' => true, 'resources' => ['default' => ['flock'], 'foo' => ['semaphore']]]];
        yield [['resource' => [['name' => 'foo', 'value' => 'flock']]], ['enabled' => true, 'resources' => ['foo' => ['flock']]]];
        yield [['resource' => [['name' => 'foo', 'value' => 'flock'], ['name' => 'foo', 'value' => 'semaphore']]], ['enabled' => true, 'resources' => ['foo' => ['flock', 'semaphore']]]];
        yield [['resource' => [['name' => 'foo', 'value' => 'flock'], ['name' => 'bar', 'value' => 'semaphore']]], ['enabled' => true, 'resources' => ['foo' => ['flock'], 'bar' => ['semaphore']]]];
        yield [['resource' => [['name' => 'foo', 'value' => 'flock'], ['name' => 'foo', 'value' => 'semaphore'], ['name' => 'bar', 'value' => 'semaphore']]], ['enabled' => true, 'resources' => ['foo' => ['flock', 'semaphore'], 'bar' => ['semaphore']]]];

        yield [['enabled' => false, 'resource' => ['flock']], ['enabled' => false, 'resources' => ['default' => ['flock']]]];
        yield [['enabled' => false, 'resource' => ['flock', ['name' => 'foo', 'value' => 'semaphore']]], ['enabled' => false, 'resources' => ['default' => ['flock'], 'foo' => ['semaphore']]]];
        yield [['enabled' => false, 'resource' => [['name' => 'foo', 'value' => 'flock']]], ['enabled' => false, 'resources' => ['foo' => ['flock']]]];
        yield [['enabled' => false, 'resource' => [['name' => 'foo', 'value' => 'flock'], ['name' => 'foo', 'value' => 'semaphore']]], ['enabled' => false, 'resources' => ['foo' => ['flock', 'semaphore']]]];
        yield [['enabled' => false, 'resource' => [['name' => 'foo', 'value' => 'flock'], ['name' => 'bar', 'value' => 'semaphore']]], ['enabled' => false, 'resources' => ['foo' => ['flock'], 'bar' => ['semaphore']]]];
        yield [['enabled' => false, 'resource' => [['name' => 'foo', 'value' => 'flock'], ['name' => 'foo', 'value' => 'semaphore'], ['name' => 'bar', 'value' => 'semaphore']]], ['enabled' => false, 'resources' => ['foo' => ['flock', 'semaphore'], 'bar' => ['semaphore']]]];
    }

    public function testLockMergeConfigs()
    {
        $processor = new Processor();
        $configuration = new Configuration(true);
        $config = $processor->processConfiguration($configuration, [
            [
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => ['log' => true],
                'lock' => [
                    'payload' => 'flock',
                ],
            ],
            [
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => ['log' => true],
                'lock' => [
                    'payload' => 'semaphore',
                ],
            ],
        ]);

        $this->assertEquals(
            [
                'enabled' => true,
                'resources' => [
                    'payload' => ['semaphore'],
                ],
            ],
            $config['lock']
        );
    }

    /**
     * @dataProvider provideValidSemaphoreConfigurationTests
     */
    public function testValidSemaphoreConfiguration($semaphoreConfig, $processedConfig)
    {
        $processor = new Processor();
        $configuration = new Configuration(true);
        $config = $processor->processConfiguration($configuration, [
            [
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => ['log' => true],
                'semaphore' => $semaphoreConfig,
            ],
        ]);

        $this->assertArrayHasKey('semaphore', $config);

        $this->assertEquals($processedConfig, $config['semaphore']);
    }

    public static function provideValidSemaphoreConfigurationTests()
    {
        yield [null, ['enabled' => true, 'resources' => []]];

        yield ['redis://default', ['enabled' => true, 'resources' => ['default' => 'redis://default']]];
        yield [['foo' => 'redis://foo', 'bar' => 'redis://bar'], ['enabled' => true, 'resources' => ['foo' => 'redis://foo', 'bar' => 'redis://bar']]];
        yield [['default' => 'redis://default'], ['enabled' => true, 'resources' => ['default' => 'redis://default']]];

        yield [['enabled' => false, 'redis://default'], ['enabled' => false, 'resources' => ['default' => 'redis://default']]];
        yield [['enabled' => false, 'foo' => 'redis://foo', 'bar' => 'redis://bar'], ['enabled' => false, 'resources' => ['foo' => 'redis://foo', 'bar' => 'redis://bar']]];
        yield [['enabled' => false, 'default' => 'redis://default'], ['enabled' => false, 'resources' => ['default' => 'redis://default']]];

        yield [['resources' => 'redis://default'], ['enabled' => true, 'resources' => ['default' => 'redis://default']]];
        yield [['resources' => ['foo' => 'redis://foo', 'bar' => 'redis://bar']], ['enabled' => true, 'resources' => ['foo' => 'redis://foo', 'bar' => 'redis://bar']]];
        yield [['resources' => ['default' => 'redis://default']], ['enabled' => true, 'resources' => ['default' => 'redis://default']]];

        yield [['enabled' => false, 'resources' => 'redis://default'], ['enabled' => false, 'resources' => ['default' => 'redis://default']]];
        yield [['enabled' => false, 'resources' => ['foo' => 'redis://foo', 'bar' => 'redis://bar']], ['enabled' => false, 'resources' => ['foo' => 'redis://foo', 'bar' => 'redis://bar']]];
        yield [['enabled' => false, 'resources' => ['default' => 'redis://default']], ['enabled' => false, 'resources' => ['default' => 'redis://default']]];

        // xml

        yield [['resource' => ['redis://default']], ['enabled' => true, 'resources' => ['default' => 'redis://default']]];
        yield [['resource' => ['redis://default', ['name' => 'foo', 'value' => 'redis://default']]], ['enabled' => true, 'resources' => ['default' => 'redis://default', 'foo' => 'redis://default']]];
        yield [['resource' => [['name' => 'foo', 'value' => 'redis://default']]], ['enabled' => true, 'resources' => ['foo' => 'redis://default']]];
        yield [['resource' => [['name' => 'foo', 'value' => 'redis://default'], ['name' => 'bar', 'value' => 'redis://default']]], ['enabled' => true, 'resources' => ['foo' => 'redis://default', 'bar' => 'redis://default']]];

        yield [['enabled' => false, 'resource' => ['redis://default']], ['enabled' => false, 'resources' => ['default' => 'redis://default']]];
        yield [['enabled' => false, 'resource' => ['redis://default', ['name' => 'foo', 'value' => 'redis://default']]], ['enabled' => false, 'resources' => ['default' => 'redis://default', 'foo' => 'redis://default']]];
        yield [['enabled' => false, 'resource' => [['name' => 'foo', 'value' => 'redis://default']]], ['enabled' => false, 'resources' => ['foo' => 'redis://default']]];
        yield [['enabled' => false, 'resource' => [['name' => 'foo', 'value' => 'redis://foo'], ['name' => 'bar', 'value' => 'redis://bar']]], ['enabled' => false, 'resources' => ['foo' => 'redis://foo', 'bar' => 'redis://bar']]];
    }

    public function testItShowANiceMessageIfTwoMessengerBusesAreConfiguredButNoDefaultBus()
    {
        $expectedMessage = 'You must specify the "default_bus" if you define more than one bus.';
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($expectedMessage);
        $processor = new Processor();
        $configuration = new Configuration(true);

        $processor->processConfiguration($configuration, [
            'framework' => [
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => ['log' => true],
                'messenger' => [
                    'default_bus' => null,
                    'buses' => [
                        'first_bus' => [],
                        'second_bus' => [],
                    ],
                ],
            ],
        ]);
    }

    public function testBusMiddlewareDontMerge()
    {
        $processor = new Processor();
        $configuration = new Configuration(true);
        $config = $processor->processConfiguration($configuration, [
            [
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => ['log' => true],
                'messenger' => [
                    'default_bus' => 'existing_bus',
                    'buses' => [
                        'existing_bus' => [
                            'middleware' => 'existing_bus.middleware',
                        ],
                        'common_bus' => [
                            'default_middleware' => false,
                            'middleware' => 'common_bus.old_middleware',
                        ],
                    ],
                ],
            ],
            [
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => ['log' => true],
                'messenger' => [
                    'buses' => [
                        'common_bus' => [
                            'middleware' => 'common_bus.new_middleware',
                        ],
                        'new_bus' => [
                            'middleware' => 'new_bus.middleware',
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertEquals(
            [
                'existing_bus' => [
                    'default_middleware' => ['enabled' => true, 'allow_no_handlers' => false, 'allow_no_senders' => true],
                    'middleware' => [
                        ['id' => 'existing_bus.middleware', 'arguments' => []],
                    ],
                ],
                'common_bus' => [
                    'default_middleware' => ['enabled' => false, 'allow_no_handlers' => false, 'allow_no_senders' => true],
                    'middleware' => [
                        ['id' => 'common_bus.new_middleware', 'arguments' => []],
                    ],
                ],
                'new_bus' => [
                    'default_middleware' => ['enabled' => true, 'allow_no_handlers' => false, 'allow_no_senders' => true],
                    'middleware' => [
                        ['id' => 'new_bus.middleware', 'arguments' => []],
                    ],
                ],
            ],
            $config['messenger']['buses']
        );
    }

    public function testItErrorsWhenDefaultBusDoesNotExist()
    {
        $processor = new Processor();
        $configuration = new Configuration(true);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The specified default bus "foo" is not configured. Available buses are "bar", "baz".');

        $processor->processConfiguration($configuration, [
            [
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => ['log' => true],
                'messenger' => [
                    'default_bus' => 'foo',
                    'buses' => [
                        'bar' => null,
                        'baz' => null,
                    ],
                ],
            ],
        ]);
    }

    public function testLockCanBeDisabled()
    {
        $processor = new Processor();
        $configuration = new Configuration(true);

        $config = $processor->processConfiguration($configuration, [
            [
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => ['log' => true],
                'lock' => ['enabled' => false],
            ],
        ]);

        $this->assertFalse($config['lock']['enabled']);
    }

    public function testEnabledLockNeedsResources()
    {
        $processor = new Processor();
        $configuration = new Configuration(true);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid configuration for path "framework.lock": At least one resource must be defined.');

        $processor->processConfiguration($configuration, [
            [
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => ['log' => true],
                'lock' => ['enabled' => true],
            ],
        ]);
    }

    public function testSerializerJsonDetailedErrorMessagesEnabledWhenDefaultContextIsConfigured()
    {
        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(true), [
            [
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => ['log' => true],
                'serializer' => [
                    'default_context' => [
                        'foo' => 'bar',
                    ],
                ],
            ],
        ]);

        $this->assertSame(['foo' => 'bar', JsonDecode::DETAILED_ERROR_MESSAGES => true], $config['serializer']['default_context'] ?? []);
    }

    public function testSerializerJsonDetailedErrorMessagesInDefaultContextCanBeDisabled()
    {
        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(true), [
            [
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => ['log' => true],
                'serializer' => [
                    'default_context' => [
                        'foo' => 'bar',
                        JsonDecode::DETAILED_ERROR_MESSAGES => false,
                    ],
                ],
            ],
        ]);

        $this->assertSame(['foo' => 'bar', JsonDecode::DETAILED_ERROR_MESSAGES => false], $config['serializer']['default_context'] ?? []);
    }

    public function testSerializerJsonDetailedErrorMessagesInDefaultContextCanBeDisabledWithSeveralConfigsBeingMerged()
    {
        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(true), [
            [
                'http_method_override' => false,
                'handle_all_throwables' => true,
                'php_errors' => ['log' => true],
                'serializer' => [
                    'default_context' => [
                        'foo' => 'bar',
                        JsonDecode::DETAILED_ERROR_MESSAGES => false,
                    ],
                ],
            ],
            [
                'serializer' => [
                    'default_context' => [
                        'foobar' => 'baz',
                    ],
                ],
            ],
        ]);

        $this->assertSame(['foo' => 'bar', JsonDecode::DETAILED_ERROR_MESSAGES => false, 'foobar' => 'baz'], $config['serializer']['default_context'] ?? []);
    }

    public function testScopedHttpClientsInheritRateLimiterAndRetryFailedConfiguration()
    {
        $processor = new Processor();
        $configuration = new Configuration(true);

        $config = $processor->processConfiguration($configuration, [[
            'http_client' => [
                'default_options' => ['rate_limiter' => 'default_limiter', 'retry_failed' => ['max_retries' => 77]],
                'scoped_clients' => [
                    'foo' => ['base_uri' => 'http://example.com'],
                    'bar' => ['base_uri' => 'http://example.com', 'rate_limiter' => true, 'retry_failed' => true],
                    'baz' => ['base_uri' => 'http://example.com', 'rate_limiter' => false, 'retry_failed' => false],
                    'qux' => ['base_uri' => 'http://example.com', 'rate_limiter' => 'foo_limiter', 'retry_failed' => ['max_retries' => 88, 'delay' => 999]],
                ],
            ],
        ]]);

        $scopedClients = $config['http_client']['scoped_clients'];

        $this->assertSame('default_limiter', $scopedClients['foo']['rate_limiter']);
        $this->assertTrue($scopedClients['foo']['retry_failed']['enabled']);
        $this->assertSame(77, $scopedClients['foo']['retry_failed']['max_retries']);
        $this->assertSame(1000, $scopedClients['foo']['retry_failed']['delay']);

        $this->assertSame('default_limiter', $scopedClients['bar']['rate_limiter']);
        $this->assertTrue($scopedClients['bar']['retry_failed']['enabled']);
        $this->assertSame(77, $scopedClients['bar']['retry_failed']['max_retries']);
        $this->assertSame(1000, $scopedClients['bar']['retry_failed']['delay']);

        $this->assertNull($scopedClients['baz']['rate_limiter']);
        $this->assertFalse($scopedClients['baz']['retry_failed']['enabled']);
        $this->assertSame(3, $scopedClients['baz']['retry_failed']['max_retries']);
        $this->assertSame(1000, $scopedClients['baz']['retry_failed']['delay']);

        $this->assertSame('foo_limiter', $scopedClients['qux']['rate_limiter']);
        $this->assertTrue($scopedClients['qux']['retry_failed']['enabled']);
        $this->assertSame(88, $scopedClients['qux']['retry_failed']['max_retries']);
        $this->assertSame(999, $scopedClients['qux']['retry_failed']['delay']);
    }

    public function testSerializerJsonDetailedErrorMessagesEnabledByDefaultWithDebugEnabled()
    {
        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(true), [
            [
                'serializer' => null,
            ],
        ]);

        $this->assertSame([JsonDecode::DETAILED_ERROR_MESSAGES => true], $config['serializer']['default_context'] ?? []);
    }

    public function testSerializerJsonDetailedErrorMessagesNotSetByDefaultWithDebugDisabled()
    {
        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(false), [
            [
                'serializer' => null,
            ],
        ]);

        $this->assertSame([], $config['serializer']['default_context'] ?? []);
    }

    protected static function getBundleDefaultConfig()
    {
        return [
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'trust_x_sendfile_type_header' => '%env(bool:default::SYMFONY_TRUST_X_SENDFILE_TYPE_HEADER)%',
            'ide' => '%env(default::SYMFONY_IDE)%',
            'default_locale' => 'en',
            'enabled_locales' => [],
            'set_locale_from_accept_language' => false,
            'set_content_language_from_locale' => false,
            'secret' => 's3cr3t',
            'trusted_hosts' => ['%env(default::SYMFONY_TRUSTED_HOSTS)%'],
            'trusted_proxies' => ['%env(default::SYMFONY_TRUSTED_PROXIES)%'],
            'trusted_headers' => ['%env(default::SYMFONY_TRUSTED_HEADERS)%'],
            'csrf_protection' => [
                'enabled' => null,
                'cookie_name' => 'csrf-token',
                'check_header' => false,
                'stateless_token_ids' => [],
            ],
            'form' => [
                'enabled' => !class_exists(FullStack::class),
                'csrf_protection' => [
                    'enabled' => null, // defaults to csrf_protection.enabled
                    'field_name' => '_token',
                    'field_attr' => ['data-controller' => 'csrf-protection'],
                    'token_id' => null,
                ],
            ],
            'esi' => ['enabled' => false],
            'ssi' => ['enabled' => false],
            'fragments' => [
                'enabled' => false,
                'path' => '/_fragment',
                'hinclude_default_template' => null,
            ],
            'profiler' => [
                'enabled' => false,
                'only_exceptions' => false,
                'only_main_requests' => false,
                'dsn' => 'file:%kernel.cache_dir%/profiler',
                'collect' => true,
                'collect_parameter' => null,
                'collect_serializer_data' => false,
            ],
            'translator' => [
                'enabled' => !class_exists(FullStack::class),
                'fallbacks' => [],
                'cache_dir' => '%kernel.cache_dir%/translations',
                'logging' => false,
                'formatter' => 'translator.formatter.default',
                'paths' => [],
                'default_path' => '%kernel.project_dir%/translations',
                'pseudo_localization' => [
                    'enabled' => false,
                    'accents' => true,
                    'expansion_factor' => 1.0,
                    'brackets' => true,
                    'parse_html' => false,
                    'localizable_html_attributes' => [],
                ],
                'providers' => [],
            ],
            'validation' => [
                'enabled' => !class_exists(FullStack::class),
                'enable_attributes' => !class_exists(FullStack::class),
                'static_method' => ['loadValidatorMetadata'],
                'translation_domain' => 'validators',
                'mapping' => [
                    'paths' => [],
                ],
                'auto_mapping' => [],
                'not_compromised_password' => [
                    'enabled' => true,
                    'endpoint' => null,
                ],
                'email_validation_mode' => 'html5',
            ],
            'annotations' => [
                'enabled' => false,
            ],
            'serializer' => [
                'default_context' => ['foo' => 'bar', JsonDecode::DETAILED_ERROR_MESSAGES => true],
                'enabled' => true,
                'enable_attributes' => !class_exists(FullStack::class),
                'mapping' => ['paths' => []],
                'named_serializers' => [],
            ],
            'property_access' => [
                'enabled' => true,
                'magic_call' => false,
                'magic_get' => true,
                'magic_set' => true,
                'throw_exception_on_invalid_index' => false,
                'throw_exception_on_invalid_property_path' => true,
            ],
            'type_info' => [
                'enabled' => !class_exists(FullStack::class) && class_exists(Type::class),
            ],
            'property_info' => [
                'enabled' => !class_exists(FullStack::class),
            ],
            'router' => [
                'enabled' => false,
                'default_uri' => null,
                'http_port' => 80,
                'https_port' => 443,
                'strict_requirements' => true,
                'utf8' => true,
                'cache_dir' => '%kernel.build_dir%',
            ],
            'session' => [
                'enabled' => false,
                'storage_factory_id' => 'session.storage.factory.native',
                'cookie_httponly' => true,
                'cookie_samesite' => 'lax',
                'cookie_secure' => 'auto',
                'metadata_update_threshold' => 0,
            ],
            'request' => [
                'enabled' => false,
                'formats' => [],
            ],
            'assets' => [
                'enabled' => !class_exists(FullStack::class),
                'version_strategy' => null,
                'version' => null,
                'version_format' => '%%s?%%s',
                'base_path' => '',
                'base_urls' => [],
                'packages' => [],
                'json_manifest_path' => null,
                'strict_mode' => false,
            ],
            'asset_mapper' => [
                'enabled' => !class_exists(FullStack::class),
                'paths' => [],
                'excluded_patterns' => [],
                'server' => true,
                'public_prefix' => '/assets/',
                'missing_import_mode' => 'warn',
                'extensions' => [],
                'importmap_path' => '%kernel.project_dir%/importmap.php',
                'importmap_polyfill' => 'es-module-shims',
                'vendor_dir' => '%kernel.project_dir%/assets/vendor',
                'importmap_script_attributes' => [],
                'exclude_dotfiles' => true,
            ],
            'cache' => [
                'pools' => [],
                'app' => 'cache.adapter.filesystem',
                'system' => 'cache.adapter.system',
                'directory' => '%kernel.cache_dir%/pools/app',
                'default_redis_provider' => 'redis://localhost',
                'default_memcached_provider' => 'memcached://localhost',
                'default_doctrine_dbal_provider' => 'database_connection',
                'default_pdo_provider' => ContainerBuilder::willBeAvailable('doctrine/dbal', Connection::class, ['symfony/framework-bundle']) && class_exists(DoctrineAdapter::class) ? 'database_connection' : null,
                'prefix_seed' => '_%kernel.project_dir%.%kernel.container_class%',
            ],
            'workflows' => [
                'enabled' => false,
                'workflows' => [],
            ],
            'php_errors' => [
                'log' => true,
                'throw' => true,
            ],
            'web_link' => [
                'enabled' => !class_exists(FullStack::class),
            ],
            'lock' => [
                'enabled' => !class_exists(FullStack::class),
                'resources' => [
                    'default' => [
                        class_exists(SemaphoreStore::class) && SemaphoreStore::isSupported() ? 'semaphore' : 'flock',
                    ],
                ],
            ],
            'semaphore' => [
                'enabled' => !class_exists(FullStack::class),
                'resources' => [
                ],
            ],
            'messenger' => [
                'enabled' => !class_exists(FullStack::class) && interface_exists(MessageBusInterface::class),
                'routing' => [],
                'transports' => [],
                'failure_transport' => null,
                'serializer' => [
                    'default_serializer' => 'messenger.transport.native_php_serializer',
                    'symfony_serializer' => [
                        'format' => 'json',
                        'context' => [],
                    ],
                ],
                'default_bus' => null,
                'buses' => ['messenger.bus.default' => ['default_middleware' => ['enabled' => true, 'allow_no_handlers' => false, 'allow_no_senders' => true], 'middleware' => []]],
                'stop_worker_on_signals' => [],
            ],
            'disallow_search_engine_index' => true,
            'http_client' => [
                'enabled' => !class_exists(FullStack::class) && class_exists(HttpClient::class),
                'scoped_clients' => [],
            ],
            'mailer' => [
                'dsn' => null,
                'transports' => [],
                'enabled' => !class_exists(FullStack::class) && class_exists(Mailer::class),
                'message_bus' => null,
                'headers' => [],
            ],
            'notifier' => [
                'enabled' => !class_exists(FullStack::class) && class_exists(Notifier::class),
                'message_bus' => null,
                'chatter_transports' => [],
                'texter_transports' => [],
                'channel_policy' => [],
                'admin_recipients' => [],
                'notification_on_failed_messages' => false,
            ],
            'error_controller' => 'error_controller',
            'secrets' => [
                'enabled' => true,
                'vault_directory' => '%kernel.project_dir%/config/secrets/%kernel.runtime_environment%',
                'local_dotenv_file' => '%kernel.project_dir%/.env.%kernel.environment%.local',
                'decryption_env_var' => 'base64:default::SYMFONY_DECRYPTION_SECRET',
            ],
            'http_cache' => [
                'enabled' => false,
                'debug' => '%kernel.debug%',
                'private_headers' => [],
                'skip_response_headers' => [],
            ],
            'rate_limiter' => [
                'enabled' => !class_exists(FullStack::class) && class_exists(TokenBucketLimiter::class),
                'limiters' => [],
            ],
            'uid' => [
                'enabled' => !class_exists(FullStack::class) && class_exists(UuidFactory::class),
                'default_uuid_version' => 7,
                'name_based_uuid_version' => 5,
                'time_based_uuid_version' => 7,
            ],
            'html_sanitizer' => [
                'enabled' => !class_exists(FullStack::class) && class_exists(HtmlSanitizer::class),
                'sanitizers' => [],
            ],
            'scheduler' => [
                'enabled' => !class_exists(FullStack::class) && class_exists(SchedulerTransportFactory::class),
            ],
            'exceptions' => [],
            'webhook' => [
                'enabled' => !class_exists(FullStack::class) && class_exists(WebhookController::class),
                'routing' => [],
                'message_bus' => 'messenger.default_bus',
            ],
            'remote-event' => [
                'enabled' => !class_exists(FullStack::class) && class_exists(RemoteEvent::class),
            ],
            'feature_flag' => [
                'enabled' => !class_exists(FullStack::class) && class_exists(FeatureChecker::class),
            ],
        ];
    }

    public function testNamedSerializersReservedName()
    {
        $processor = new Processor();
        $configuration = new Configuration(true);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Invalid configuration for path "framework.serializer.named_serializers": "default" is a reserved name.');

        $processor->processConfiguration($configuration, [[
            'serializer' => [
                'named_serializers' => [
                    'default' => ['include_built_in_normalizers' => false],
                ],
            ],
        ]]);
    }
}
