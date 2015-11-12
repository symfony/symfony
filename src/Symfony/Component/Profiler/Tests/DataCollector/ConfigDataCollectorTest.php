<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Profiler\Tests\DataCollector;

use Symfony\Component\Profiler\DataCollector\ConfigDataCollector;

/**
 * ConfigDataCollectorTest.
 *
 * @author Jelte Steijaert <jelte@khepri.be>
 */
class ConfigDataCollectorTest extends \PHPUnit_Framework_TestCase
{
    public function testCollect()
    {
        $c = new ConfigDataCollector('Test Suite', 'test');

        $profileData = $c->getCollectedData();
        $profileData->setToken('test');

        $this->assertInstanceOf('Symfony\Component\Profiler\ProfileData\ConfigData', $profileData);

        $this->assertSame('Test Suite', $profileData->getApplicationName());
        $this->assertSame('test', $profileData->getApplicationVersion());
        $this->assertSame('n/a', $profileData->getEnv());
        $this->assertSame('n/a', $profileData->isDebug());
        $this->assertSame('n/a', $profileData->getAppName());
        $this->assertSame(PHP_VERSION, $profileData->getPhpVersion());
        $this->assertSame('n/a', $profileData->getSymfonyVersion());
        $this->assertSame('n/a', $profileData->getSymfonyState());

        $this->assertSame('test', $profileData->getToken());

        // if else clause because we don't know it
        if (extension_loaded('xdebug')) {
            $this->assertTrue($profileData->hasXdebug());
        } else {
            $this->assertFalse($profileData->hasXdebug());
        }

        // if else clause because we don't know it
        if (((extension_loaded('eaccelerator') && ini_get('eaccelerator.enable'))
                ||
                (extension_loaded('apc') && ini_get('apc.enabled'))
                ||
                (extension_loaded('Zend OPcache') && ini_get('opcache.enable'))
                ||
                (extension_loaded('xcache') && ini_get('xcache.cacher'))
                ||
                (extension_loaded('wincache') && ini_get('wincache.ocenabled')))) {
            $this->assertTrue($profileData->hasAccelerator());
        } else {
            $this->assertFalse($profileData->hasAccelerator());
        }

        $this->assertEquals(extension_loaded('eaccelerator') && ini_get('eaccelerator.enable'), $profileData->hasEAccelerator());
        $this->assertEquals(extension_loaded('apc') && ini_get('apc.enabled'), $profileData->hasApc());
        $this->assertEquals(extension_loaded('wincache') && ini_get('wincache.ocenabled'), $profileData->hasWinCache());
        $this->assertEquals(extension_loaded('xcache') && ini_get('xcache.cacher'), $profileData->hasXCache());
        $this->assertEquals(extension_loaded('Zend OPcache') && ini_get('opcache.enable'), $profileData->hasZendOpcache());
        $this->assertEquals(php_sapi_name(), $profileData->getSapiName());

        $this->assertEmpty($profileData->getBundles());
    }
}
