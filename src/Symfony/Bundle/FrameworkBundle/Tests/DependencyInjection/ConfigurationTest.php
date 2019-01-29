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
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Lock\Store\SemaphoreStore;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Serializer\Serializer;

class ConfigurationTest extends TestCase
{
    public function testDefaultConfig()
    {
        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(true), [['secret' => 's3cr3t']]);

        $this->assertEquals(
            array_merge(['secret' => 's3cr3t', 'trusted_hosts' => []], self::getBundleDefaultConfig()),
            $config
        );
    }

    public function testDoNoDuplicateDefaultFormResources()
    {
        $input = ['templating' => [
            'form' => ['resources' => ['FrameworkBundle:Form']],
            'engines' => ['php'],
        ]];

        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(true), [$input]);

        $this->assertEquals(['FrameworkBundle:Form'], $config['templating']['form']['resources']);
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
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testInvalidSessionName($sessionName)
    {
        $processor = new Processor();
        $processor->processConfiguration(
            new Configuration(true),
            [['session' => ['name' => $sessionName]]]
        );
    }

    public function getTestInvalidSessionName()
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
        $config = $processor->processConfiguration($configuration, [['assets' => null]]);

        $defaultConfig = [
            'enabled' => true,
            'version_strategy' => null,
            'version' => null,
            'version_format' => '%%s?%%s',
            'base_path' => '',
            'base_urls' => [],
            'packages' => [],
            'json_manifest_path' => null,
        ];

        $this->assertEquals($defaultConfig, $config['assets']);
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
                'assets' => [
                    'packages' => [
                        $packageName => [],
                    ],
                ],
            ],
        ]);

        $this->assertArrayHasKey($packageName, $config['assets']['packages']);
    }

    public function provideValidAssetsPackageNameConfigurationTests()
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
        if (method_exists($this, 'expectException')) {
            $this->expectException(InvalidConfigurationException::class);
            $this->expectExceptionMessage($expectedMessage);
        } else {
            $this->setExpectedException(InvalidConfigurationException::class, $expectedMessage);
        }

        $processor = new Processor();
        $configuration = new Configuration(true);
        $processor->processConfiguration($configuration, [
                [
                    'assets' => $assetConfig,
                ],
            ]);
    }

    public function provideInvalidAssetConfigurationTests()
    {
        // helper to turn config into embedded package config
        $createPackageConfig = function (array $packageConfig) {
            return [
                'base_urls' => '//example.com',
                'version' => 1,
                'packages' => [
                    'foo' => $packageConfig,
                ],
            ];
        };

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

    protected static function getBundleDefaultConfig()
    {
        return [
            'http_method_override' => true,
            'ide' => null,
            'default_locale' => 'en',
            'csrf_protection' => [
                'enabled' => false,
            ],
            'form' => [
                'enabled' => !class_exists(FullStack::class),
                'csrf_protection' => [
                    'enabled' => null, // defaults to csrf_protection.enabled
                    'field_name' => '_token',
                ],
            ],
            'esi' => ['enabled' => false],
            'ssi' => ['enabled' => false],
            'fragments' => [
                'enabled' => false,
                'path' => '/_fragment',
            ],
            'profiler' => [
                'enabled' => false,
                'only_exceptions' => false,
                'only_master_requests' => false,
                'dsn' => 'file:%kernel.cache_dir%/profiler',
                'collect' => true,
            ],
            'translator' => [
                'enabled' => !class_exists(FullStack::class),
                'fallbacks' => ['en'],
                'logging' => false,
                'formatter' => 'translator.formatter.default',
                'paths' => [],
                'default_path' => '%kernel.project_dir%/translations',
            ],
            'validation' => [
                'enabled' => !class_exists(FullStack::class),
                'enable_annotations' => !class_exists(FullStack::class),
                'static_method' => ['loadValidatorMetadata'],
                'translation_domain' => 'validators',
                'mapping' => [
                    'paths' => [],
                ],
            ],
            'annotations' => [
                'cache' => 'php_array',
                'file_cache_dir' => '%kernel.cache_dir%/annotations',
                'debug' => true,
                'enabled' => true,
            ],
            'serializer' => [
                'enabled' => !class_exists(FullStack::class),
                'enable_annotations' => !class_exists(FullStack::class),
                'mapping' => ['paths' => []],
            ],
            'property_access' => [
                'magic_call' => false,
                'throw_exception_on_invalid_index' => false,
            ],
            'property_info' => [
                'enabled' => !class_exists(FullStack::class),
            ],
            'router' => [
                'enabled' => false,
                'http_port' => 80,
                'https_port' => 443,
                'strict_requirements' => true,
                'utf8' => false,
            ],
            'session' => [
                'enabled' => false,
                'storage_id' => 'session.storage.native',
                'handler_id' => 'session.handler.native_file',
                'cookie_httponly' => true,
                'cookie_samesite' => null,
                'gc_probability' => 1,
                'save_path' => '%kernel.cache_dir%/sessions',
                'metadata_update_threshold' => 0,
            ],
            'request' => [
                'enabled' => false,
                'formats' => [],
            ],
            'templating' => [
                'enabled' => false,
                'hinclude_default_template' => null,
                'form' => [
                    'resources' => ['FrameworkBundle:Form'],
                ],
                'engines' => [],
                'loaders' => [],
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
            ],
            'cache' => [
                'pools' => [],
                'app' => 'cache.adapter.filesystem',
                'system' => 'cache.adapter.system',
                'directory' => '%kernel.cache_dir%/pools',
                'default_redis_provider' => 'redis://localhost',
                'default_memcached_provider' => 'memcached://localhost',
                'default_pdo_provider' => class_exists(Connection::class) ? 'database_connection' : null,
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
            'messenger' => [
                'enabled' => !class_exists(FullStack::class) && interface_exists(MessageBusInterface::class),
                'routing' => [],
                'transports' => [],
                'serializer' => [
                    'id' => !class_exists(FullStack::class) && class_exists(Serializer::class) ? 'messenger.transport.symfony_serializer' : null,
                    'format' => 'json',
                    'context' => [],
                ],
                'default_bus' => null,
                'buses' => ['messenger.bus.default' => ['default_middleware' => true, 'middleware' => []]],
            ],
        ];
    }
}
