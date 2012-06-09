<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace ymfony\Tests\Component\ClassLoader;

use Symfony\Component\ClassLoader\XcacheUniversalClassLoader;

class XcacheUniversalClassLoaderTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        if (!extension_loaded('xcache')) {
            $this->markTestSkipped('The xcache extension is not available.');
        }

        if (!ini_get('xcache.cacher')) {
            $this->markTestSkipped('The xcache extension is available, but not enabled');
        }
    }


    public function testConstructorWithPrfix()
    {
        $testPrefix = 'test.prefix.';
        $loader = new XcacheUniversalClassLoader('test.prefix.');
        $this->assertEquals($loader->getPrefix(), $testPrefix);
    }

    public function testConstructorWithOutPrefix()
    {
        $loader = new XcacheUniversalClassLoader();
        $this->assertEquals($loader->getPrefix(), 'xcache.prefix.');
    }

    /**
     * Because of a known unfeature in xcache this test can cover the fallback only.
     * @see: http://xcache.lighttpd.net/ticket/228
     */
    public function testFallbackToUniversalClassLoader()
    {
        $loader = new XcacheUniversalClassLoader('test.prefix.');
        $fixturesDir =  __DIR__.DIRECTORY_SEPARATOR.'Fixtures';

        $loader->registerNamespace('Xcache\Namespaced',$fixturesDir);
        $this->assertEquals($loader->findFile('\Xcache\Namespaced\FooBar'), $fixturesDir . '/Xcache/Namespaced/FooBar.php', '__construct() takes a prefix as its first argument');
    }

}
