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
        $config = $processor->processConfiguration(new Configuration(true), array(array('secret' => 's3cr3t')));

        $this->assertEquals(
            array_merge(array('secret' => 's3cr3t', 'trusted_hosts' => array()), self::getBundleDefaultConfig()),
            $config
        );
    }

    public function testDoNoDuplicateDefaultFormResources()
    {
        $input = array('templating' => array(
            'form' => array('resources' => array('FrameworkBundle:Form')),
            'engines' => array('php'),
        ));

        $processor = new Processor();
        $config = $processor->processConfiguration(new Configuration(true), array($input));

        $this->assertEquals(array('FrameworkBundle:Form'), $config['templating']['form']['resources']);
    }

    /**
     * @group legacy
     * @expectedDeprecation The "framework.trusted_proxies" configuration key has been deprecated in Symfony 3.3. Use the Request::setTrustedProxies() method in your front controller instead.
     */
    public function testTrustedProxiesSetToNullIsDeprecated()
    {
        $processor = new Processor();
        $configuration = new Configuration(true);
        $processor->processConfiguration($configuration, array(array('trusted_proxies' => null)));
    }

    /**
     * @group legacy
     * @expectedDeprecation The "framework.trusted_proxies" configuration key has been deprecated in Symfony 3.3. Use the Request::setTrustedProxies() method in your front controller instead.
     */
    public function testTrustedProxiesSetToEmptyArrayIsDeprecated()
    {
        $processor = new Processor();
        $configuration = new Configuration(true);
        $processor->processConfiguration($configuration, array(array('trusted_proxies' => array())));
    }

    /**
     * @group legacy
     * @expectedDeprecation The "framework.trusted_proxies" configuration key has been deprecated in Symfony 3.3. Use the Request::setTrustedProxies() method in your front controller instead.
     */
    public function testTrustedProxiesSetToNonEmptyArrayIsInvalid()
    {
        $processor = new Processor();
        $configuration = new Configuration(true);
        $processor->processConfiguration($configuration, array(array('trusted_proxies' => array('127.0.0.1'))));
    }

    /**
     * @group legacy
     * @dataProvider getTestValidTrustedProxiesData
     */
    public function testValidTrustedProxies($trustedProxies, $processedProxies)
    {
        $processor = new Processor();
        $configuration = new Configuration(true);
        $config = $processor->processConfiguration($configuration, array(array(
            'secret' => 's3cr3t',
            'trusted_proxies' => $trustedProxies,
        )));

        $this->assertEquals($processedProxies, $config['trusted_proxies']);
    }

    public function getTestValidTrustedProxiesData()
    {
        return array(
            array(array('127.0.0.1'), array('127.0.0.1')),
            array(array('::1'), array('::1')),
            array(array('127.0.0.1', '::1'), array('127.0.0.1', '::1')),
            array(null, array()),
            array(false, array()),
            array(array(), array()),
            array(array('10.0.0.0/8'), array('10.0.0.0/8')),
            array(array('::ffff:0:0/96'), array('::ffff:0:0/96')),
            array(array('0.0.0.0/0'), array('0.0.0.0/0')),
        );
    }

    /**
     * @group legacy
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testInvalidTypeTrustedProxies()
    {
        $processor = new Processor();
        $configuration = new Configuration(true);
        $processor->processConfiguration($configuration, array(
            array(
                'secret' => 's3cr3t',
                'trusted_proxies' => 'Not an IP address',
            ),
        ));
    }

    /**
     * @group legacy
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testInvalidValueTrustedProxies()
    {
        $processor = new Processor();
        $configuration = new Configuration(true);

        $processor->processConfiguration($configuration, array(
            array(
                'secret' => 's3cr3t',
                'trusted_proxies' => array('Not an IP address'),
            ),
        ));
    }

    public function testAssetsCanBeEnabled()
    {
        $processor = new Processor();
        $configuration = new Configuration(true);
        $config = $processor->processConfiguration($configuration, array(array('assets' => null)));

        $defaultConfig = array(
            'enabled' => true,
            'version_strategy' => null,
            'version' => null,
            'version_format' => '%%s?%%s',
            'base_path' => '',
            'base_urls' => array(),
            'packages' => array(),
            'json_manifest_path' => null,
        );

        $this->assertEquals($defaultConfig, $config['assets']);
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
        $processor->processConfiguration($configuration, array(
                array(
                    'assets' => $assetConfig,
                ),
            ));
    }

    public function provideInvalidAssetConfigurationTests()
    {
        // helper to turn config into embedded package config
        $createPackageConfig = function (array $packageConfig) {
            return array(
                'base_urls' => '//example.com',
                'version' => 1,
                'packages' => array(
                    'foo' => $packageConfig,
                ),
            );
        };

        $config = array(
            'version' => 1,
            'version_strategy' => 'foo',
        );
        yield array($config, 'You cannot use both "version_strategy" and "version" at the same time under "assets".');
        yield array($createPackageConfig($config), 'You cannot use both "version_strategy" and "version" at the same time under "assets" packages.');

        $config = array(
            'json_manifest_path' => '/foo.json',
            'version_strategy' => 'foo',
        );
        yield array($config, 'You cannot use both "version_strategy" and "json_manifest_path" at the same time under "assets".');
        yield array($createPackageConfig($config), 'You cannot use both "version_strategy" and "json_manifest_path" at the same time under "assets" packages.');

        $config = array(
            'json_manifest_path' => '/foo.json',
            'version' => '1',
        );
        yield array($config, 'You cannot use both "version" and "json_manifest_path" at the same time under "assets".');
        yield array($createPackageConfig($config), 'You cannot use both "version" and "json_manifest_path" at the same time under "assets" packages.');
    }

    protected static function getBundleDefaultConfig()
    {
        return array(
            'http_method_override' => true,
            'trusted_proxies' => array(),
            'ide' => null,
            'default_locale' => 'en',
            'csrf_protection' => array(
                'enabled' => false,
            ),
            'form' => array(
                'enabled' => !class_exists(FullStack::class),
                'csrf_protection' => array(
                    'enabled' => null, // defaults to csrf_protection.enabled
                    'field_name' => '_token',
                ),
            ),
            'esi' => array('enabled' => false),
            'ssi' => array('enabled' => false),
            'fragments' => array(
                'enabled' => false,
                'path' => '/_fragment',
            ),
            'profiler' => array(
                'enabled' => false,
                'only_exceptions' => false,
                'only_master_requests' => false,
                'dsn' => 'file:%kernel.cache_dir%/profiler',
                'collect' => true,
                'matcher' => array(
                    'enabled' => false,
                    'ips' => array(),
                ),
            ),
            'translator' => array(
                'enabled' => !class_exists(FullStack::class),
                'fallbacks' => array('en'),
                'logging' => true,
                'formatter' => 'translator.formatter.default',
                'paths' => array(),
                'default_path' => '%kernel.project_dir%/translations',
            ),
            'validation' => array(
                'enabled' => !class_exists(FullStack::class),
                'enable_annotations' => !class_exists(FullStack::class),
                'static_method' => array('loadValidatorMetadata'),
                'translation_domain' => 'validators',
                'strict_email' => false,
                'mapping' => array(
                    'paths' => array(),
                ),
            ),
            'annotations' => array(
                'cache' => 'php_array',
                'file_cache_dir' => '%kernel.cache_dir%/annotations',
                'debug' => true,
                'enabled' => true,
            ),
            'serializer' => array(
                'enabled' => !class_exists(FullStack::class),
                'enable_annotations' => !class_exists(FullStack::class),
                'mapping' => array('paths' => array()),
            ),
            'property_access' => array(
                'magic_call' => false,
                'throw_exception_on_invalid_index' => false,
            ),
            'property_info' => array(
                'enabled' => false,
            ),
            'router' => array(
                'enabled' => false,
                'http_port' => 80,
                'https_port' => 443,
                'strict_requirements' => true,
            ),
            'session' => array(
                'enabled' => false,
                'storage_id' => 'session.storage.native',
                'handler_id' => 'session.handler.native_file',
                'cookie_httponly' => true,
                'gc_probability' => 1,
                'save_path' => '%kernel.cache_dir%/sessions',
                'metadata_update_threshold' => '0',
                'use_strict_mode' => true,
            ),
            'request' => array(
                'enabled' => false,
                'formats' => array(),
            ),
            'templating' => array(
                'enabled' => false,
                'hinclude_default_template' => null,
                'form' => array(
                    'resources' => array('FrameworkBundle:Form'),
                ),
                'engines' => array(),
                'loaders' => array(),
            ),
            'assets' => array(
                'enabled' => !class_exists(FullStack::class),
                'version_strategy' => null,
                'version' => null,
                'version_format' => '%%s?%%s',
                'base_path' => '',
                'base_urls' => array(),
                'packages' => array(),
                'json_manifest_path' => null,
            ),
            'cache' => array(
                'pools' => array(),
                'app' => 'cache.adapter.filesystem',
                'system' => 'cache.adapter.system',
                'directory' => '%kernel.cache_dir%/pools',
                'default_redis_provider' => 'redis://localhost',
                'default_memcached_provider' => 'memcached://localhost',
            ),
            'workflows' => array(
                'enabled' => false,
                'workflows' => array(),
            ),
            'php_errors' => array(
                'log' => true,
                'throw' => true,
            ),
            'web_link' => array(
                'enabled' => !class_exists(FullStack::class),
            ),
            'lock' => array(
                'enabled' => !class_exists(FullStack::class),
                'resources' => array(
                    'default' => array(
                        class_exists(SemaphoreStore::class) && SemaphoreStore::isSupported() ? 'semaphore' : 'flock',
                    ),
                ),
            ),
        );
    }
}
