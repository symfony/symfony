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

    protected static function getBundleDefaultConfig()
    {
        return array(
            'http_method_override' => true,
            'trusted_proxies' => array(),
            'ide' => null,
            'default_locale' => 'en',
            'form' => array(
                'enabled' => false,
                'csrf_protection' => array(
                    'enabled' => null, // defaults to csrf_protection.enabled
                    'field_name' => null,
                ),
            ),
            'csrf_protection' => array(
                'enabled' => false,
                'field_name' => '_token',
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
                'username' => '',
                'password' => '',
                'lifetime' => 86400,
                'collect' => true,
            ),
            'translator' => array(
                'enabled' => false,
                'fallback' => 'en',
                'logging' => true,
            ),
            'validation' => array(
                'enabled' => false,
                'enable_annotations' => false,
                'static_method' => array('loadValidatorMetadata'),
                'translation_domain' => 'validators',
                'strict_email' => false,
                'api' => version_compare(PHP_VERSION, '5.3.9', '<') ? '2.4' : '2.5-bc',
            ),
            'annotations' => array(
                'cache' => 'file',
                'file_cache_dir' => '%kernel.cache_dir%/annotations',
                'debug' => '%kernel.debug%',
            ),
            'serializer' => array(
                'enabled' => false,
            ),
            'property_access' => array(
                'magic_call' => false,
                'throw_exception_on_invalid_index' => false,
            ),
        );
    }
}
