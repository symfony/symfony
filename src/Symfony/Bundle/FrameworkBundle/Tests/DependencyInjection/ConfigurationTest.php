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

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Configuration;
use Symfony\Bundle\FullStack;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Lock\Store\SemaphoreStore;

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

    /**
     * @group legacy
     * @expectedDeprecation The "framework.trusted_proxies" configuration key has been deprecated in Symfony 3.3. Use the Request::setTrustedProxies() method in your front controller instead.
     */
    public function testTrustedProxiesSetToNullIsDeprecated()
    {
        $processor = new Processor();
        $configuration = new Configuration(true);
        $processor->processConfiguration($configuration, [['trusted_proxies' => null]]);
    }

    /**
     * @group legacy
     * @expectedDeprecation The "framework.trusted_proxies" configuration key has been deprecated in Symfony 3.3. Use the Request::setTrustedProxies() method in your front controller instead.
     */
    public function testTrustedProxiesSetToEmptyArrayIsDeprecated()
    {
        $processor = new Processor();
        $configuration = new Configuration(true);
        $processor->processConfiguration($configuration, [['trusted_proxies' => []]]);
    }

    /**
     * @group legacy
     * @expectedDeprecation The "framework.trusted_proxies" configuration key has been deprecated in Symfony 3.3. Use the Request::setTrustedProxies() method in your front controller instead.
     */
    public function testTrustedProxiesSetToNonEmptyArrayIsInvalid()
    {
        $processor = new Processor();
        $configuration = new Configuration(true);
        $processor->processConfiguration($configuration, [['trusted_proxies' => ['127.0.0.1']]]);
    }

    /**
     * @group legacy
     * @dataProvider getTestValidSessionName
     */
    public function testValidSessionName($sessionName)
    {
        $processor = new Processor();
        $config = $processor->processConfiguration(
            new Configuration(true),
            [['session' => ['name' => $sessionName]]]
        );

        $this->assertEquals($sessionName, $config['session']['name']);
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
        $this->expectException('Symfony\Component\Config\Definition\Exception\InvalidConfigurationException');
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

    /**
     * @dataProvider getTestValidTrustedProxiesData
     * @group legacy
     */
    public function testValidTrustedProxies($trustedProxies, $processedProxies)
    {
        $processor = new Processor();
        $configuration = new Configuration(true);
        $config = $processor->processConfiguration($configuration, [[
            'secret' => 's3cr3t',
            'trusted_proxies' => $trustedProxies,
        ]]);

        $this->assertEquals($processedProxies, $config['trusted_proxies']);
    }

    public function getTestValidTrustedProxiesData()
    {
        return [
            [['127.0.0.1'], ['127.0.0.1']],
            [['::1'], ['::1']],
            [['127.0.0.1', '::1'], ['127.0.0.1', '::1']],
            [null, []],
            [false, []],
            [[], []],
            [['10.0.0.0/8'], ['10.0.0.0/8']],
            [['::ffff:0:0/96'], ['::ffff:0:0/96']],
            [['0.0.0.0/0'], ['0.0.0.0/0']],
        ];
    }

    /**
     * @group legacy
     */
    public function testInvalidTypeTrustedProxies()
    {
        $this->expectException('Symfony\Component\Config\Definition\Exception\InvalidConfigurationException');
        $processor = new Processor();
        $configuration = new Configuration(true);
        $processor->processConfiguration($configuration, [
            [
                'secret' => 's3cr3t',
                'trusted_proxies' => 'Not an IP address',
            ],
        ]);
    }

    /**
     * @group legacy
     */
    public function testInvalidValueTrustedProxies()
    {
        $this->expectException('Symfony\Component\Config\Definition\Exception\InvalidConfigurationException');
        $processor = new Processor();
        $configuration = new Configuration(true);

        $processor->processConfiguration($configuration, [
            [
                'secret' => 's3cr3t',
                'trusted_proxies' => ['Not an IP address'],
            ],
        ]);
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
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($expectedMessage);

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

    /**
     * @dataProvider provideValidLockConfigurationTests
     */
    public function testValidLockConfiguration($lockConfig, $processedConfig)
    {
        $processor = new Processor();
        $configuration = new Configuration(true);
        $config = $processor->processConfiguration($configuration, [
            [
                'lock' => $lockConfig,
            ],
        ]);

        $this->assertArrayHasKey('lock', $config);

        $this->assertEquals($processedConfig, $config['lock']);
    }

    public function provideValidLockConfigurationTests()
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

    protected static function getBundleDefaultConfig()
    {
        return [
            'http_method_override' => true,
            'trusted_proxies' => [],
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
                'matcher' => [
                    'enabled' => false,
                    'ips' => [],
                ],
            ],
            'translator' => [
                'enabled' => !class_exists(FullStack::class),
                'fallbacks' => ['en'],
                'logging' => true,
                'formatter' => 'translator.formatter.default',
                'paths' => [],
                'default_path' => '%kernel.project_dir%/translations',
            ],
            'validation' => [
                'enabled' => !class_exists(FullStack::class),
                'enable_annotations' => !class_exists(FullStack::class),
                'static_method' => ['loadValidatorMetadata'],
                'translation_domain' => 'validators',
                'strict_email' => false,
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
            ],
            'session' => [
                'enabled' => false,
                'storage_id' => 'session.storage.native',
                'handler_id' => 'session.handler.native_file',
                'cookie_httponly' => true,
                'cookie_samesite' => null,
                'gc_probability' => 1,
                'save_path' => '%kernel.cache_dir%/sessions',
                'metadata_update_threshold' => '0',
                'use_strict_mode' => true,
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
        ];
    }
}
