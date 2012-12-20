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
    /**
     * @dataProvider getTestConfigTreeData
     */
    public function testConfigTree($options, $results)
    {
        $processor = new Processor();
        $configuration = new Configuration(array());
        $config = $processor->processConfiguration($configuration, array($options));

        $this->assertEquals($results, $config);
    }

    public function getTestConfigTreeData()
    {
        return array(
            array(array('secret' => 's3cr3t'), array('secret' => 's3cr3t', 'trusted_proxies' => array(), 'trust_proxy_headers' => false, 'ide' => NULL, 'annotations' => array('cache' => 'file', 'file_cache_dir' => '%kernel.cache_dir%/annotations', 'debug' => false))),
        );
    }

    /**
     * @dataProvider getTestValidTrustedProxiesData
     */
    public function testValidTrustedProxies($options, $results)
    {
        $processor = new Processor();
        $configuration = new Configuration(array());
        $config = $processor->processConfiguration($configuration, array($options));

        $this->assertEquals($results, $config);
    }

    public function getTestValidTrustedProxiesData()
    {
        return array(
            array(array('secret' => 's3cr3t', 'trusted_proxies' => array('127.0.0.1')), array('secret' => 's3cr3t', 'trusted_proxies' => array('127.0.0.1'), 'trust_proxy_headers' => false, 'ide' => NULL, 'annotations' => array('cache' => 'file', 'file_cache_dir' => '%kernel.cache_dir%/annotations', 'debug' => false))),
            array(array('secret' => 's3cr3t', 'trusted_proxies' => array('::1')), array('secret' => 's3cr3t', 'trusted_proxies' => array('::1'), 'trust_proxy_headers' => false, 'ide' => NULL, 'annotations' => array('cache' => 'file', 'file_cache_dir' => '%kernel.cache_dir%/annotations', 'debug' => false))),
            array(array('secret' => 's3cr3t', 'trusted_proxies' => array('127.0.0.1', '::1')), array('secret' => 's3cr3t', 'trusted_proxies' => array('127.0.0.1', '::1'), 'trust_proxy_headers' => false, 'ide' => NULL, 'annotations' => array('cache' => 'file', 'file_cache_dir' => '%kernel.cache_dir%/annotations', 'debug' => false))),
            array(array('secret' => 's3cr3t', 'trusted_proxies' => null), array('secret' => 's3cr3t', 'trusted_proxies' => array(), 'trust_proxy_headers' => false, 'ide' => NULL, 'annotations' => array('cache' => 'file', 'file_cache_dir' => '%kernel.cache_dir%/annotations', 'debug' => false))),
            array(array('secret' => 's3cr3t', 'trusted_proxies' => false), array('secret' => 's3cr3t', 'trusted_proxies' => array(), 'trust_proxy_headers' => false, 'ide' => NULL, 'annotations' => array('cache' => 'file', 'file_cache_dir' => '%kernel.cache_dir%/annotations', 'debug' => false))),
            array(array('secret' => 's3cr3t', 'trusted_proxies' => array()), array('secret' => 's3cr3t', 'trusted_proxies' => array(), 'trust_proxy_headers' => false, 'ide' => NULL, 'annotations' => array('cache' => 'file', 'file_cache_dir' => '%kernel.cache_dir%/annotations', 'debug' => false))),
        );
    }

    /**
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testInvalidTypeTrustedProxies()
    {
        $processor = new Processor();
        $configuration = new Configuration(array());
        $config = $processor->processConfiguration($configuration, array(array('secret' => 's3cr3t', 'trusted_proxies' => 'Not an IP address')));
    }

    /**
     * @expectedException Symfony\Component\Config\Definition\Exception\InvalidConfigurationException
     */
    public function testInvalidValueTrustedProxies()
    {
        $processor = new Processor();
        $configuration = new Configuration(array());
        $config = $processor->processConfiguration($configuration, array(array('secret' => 's3cr3t', 'trusted_proxies' => array('Not an IP address'))));
    }
}
