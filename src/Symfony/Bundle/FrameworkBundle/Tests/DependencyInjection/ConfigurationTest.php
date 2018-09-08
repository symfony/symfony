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
use Symfony\Component\Messenger\MessageBusInterface;

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

    public function getTestValidSessionName()
    {
        return array(
            array(null),
            array('PHPSESSID'),
            array('a&b'),
            array(',_-!@#$%^*(){}:<>/?'),
        );
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
            array(array('session' => array('name' => $sessionName)))
        );
    }

    public function getTestInvalidSessionName()
    {
        return array(
            array('a.b'),
            array('a['),
            array('a[]'),
            array('a[b]'),
            array('a=b'),
            array('a+b'),
        );
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
            ),
            'translator' => array(
                'enabled' => !class_exists(FullStack::class),
                'fallbacks' => array('en'),
                'logging' => false,
                'formatter' => \class_exists('MessageFormatter') ? 'translator.formatter.default' : 'translator.formatter.symfony',
                'paths' => array(),
                'default_path' => '%kernel.project_dir%/translations',
            ),
            'validation' => array(
                'enabled' => !class_exists(FullStack::class),
                'enable_annotations' => !class_exists(FullStack::class),
                'static_method' => array('loadValidatorMetadata'),
                'translation_domain' => 'validators',
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
                'enabled' => !class_exists(FullStack::class),
            ),
            'router' => array(
                'enabled' => false,
                'http_port' => 80,
                'https_port' => 443,
                'strict_requirements' => true,
                'utf8' => false,
            ),
            'session' => array(
                'enabled' => false,
                'storage_id' => 'session.storage.native',
                'handler_id' => 'session.handler.native_file',
                'cookie_httponly' => true,
                'cookie_samesite' => null,
                'gc_probability' => 1,
                'save_path' => '%kernel.cache_dir%/sessions',
                'metadata_update_threshold' => '0',
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
                'default_pdo_provider' => 'doctrine.dbal.default_connection',
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
            'messenger' => array(
                'enabled' => !class_exists(FullStack::class) && interface_exists(MessageBusInterface::class),
                'routing' => array(),
                'transports' => array(),
                'serializer' => array(
                    'id' => 'messenger.transport.symfony_serializer',
                    'format' => 'json',
                    'context' => array(),
                ),
                'default_bus' => null,
                'buses' => array('messenger.bus.default' => array('default_middleware' => true, 'middleware' => array())),
            ),
        );
    }
}
