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

use Symfony\Bundle\FrameworkBundle\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Processor;

class ConfigurationTest extends \PHPUnit_Framework_TestCase
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
        );

        $this->assertEquals($defaultConfig, $config['assets']);
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessage You cannot use both "version_strategy" and "version" at the same time under "assets".
     */
    public function testInvalidVersionStrategy()
    {
        $processor = new Processor();
        $configuration = new Configuration(true);
        $processor->processConfiguration($configuration, array(
            array(
                'assets' => array(
                    'base_urls' => '//example.com',
                    'version' => 1,
                    'version_strategy' => 'foo',
                ),
            ),
        ));
    }

    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     * @expectedExceptionMessage  You cannot use both "version_strategy" and "version" at the same time under "assets" packages.
     */
    public function testInvalidPackageVersionStrategy()
    {
        $processor = new Processor();
        $configuration = new Configuration(true);

        $processor->processConfiguration($configuration, array(
            array(
                'assets' => array(
                    'base_urls' => '//example.com',
                    'version' => 1,
                    'packages' => array(
                        'foo' => array(
                            'base_urls' => '//example.com',
                            'version' => 1,
                            'version_strategy' => 'foo',
                        ),
                    ),
                ),
            ),
        ));
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
                'enabled' => false,
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
                'enabled' => false,
                'fallbacks' => array('en'),
                'logging' => true,
                'paths' => array(),
            ),
            'validation' => array(
                'enabled' => false,
                'enable_annotations' => false,
                'static_method' => array('loadValidatorMetadata'),
                'translation_domain' => 'validators',
                'strict_email' => false,
            ),
            'annotations' => array(
                'cache' => 'php_array',
                'file_cache_dir' => '%kernel.cache_dir%/annotations',
                'debug' => true,
                'enabled' => true,
            ),
            'serializer' => array(
                'enabled' => false,
                'enable_annotations' => false,
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
                'enabled' => false,
                'version_strategy' => null,
                'version' => null,
                'version_format' => '%%s?%%s',
                'base_path' => '',
                'base_urls' => array(),
                'packages' => array(),
            ),
            'cache' => array(
                'pools' => array(),
                'app' => 'cache.adapter.filesystem',
                'system' => 'cache.adapter.system',
                'directory' => '%kernel.cache_dir%/pools',
                'default_redis_provider' => 'redis://localhost',
            ),
            'workflows' => array(),
            'php_errors' => array(
                'log' => true,
                'throw' => true,
            ),
        );
    }
}
