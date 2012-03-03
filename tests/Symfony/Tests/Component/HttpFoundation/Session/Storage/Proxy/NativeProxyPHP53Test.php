<?php

namespace Symfony\Tests\Component\HttpFoundation\Session\Storage\Proxy;

use Symfony\Component\HttpFoundation\Session\Storage\Proxy\NativeProxy;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeFileSessionHandler;

/**
 * Test class for NativeProxy.
 *
 * @runTestsInSeparateProcesses
 */
class NativeProxyPHP53Test extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        if (version_compare(phpversion(), '5.4.0', '>=')) {
            $this->markTestSkipped('Test skipped, only for PHP 5.3');
        }
    }

    public function testIsWrapper()
    {
        $proxy = new NativeProxy(new NativeFileSessionHandler());
        $this->assertFalse($proxy->isWrapper());
    }

    public function testGetSaveHandlerName()
    {
        $name = ini_get('session.save_handler');
        $proxy = new NativeProxy(new NativeFileSessionHandler());
        $this->assertEquals($name, $proxy->getSaveHandlerName());
    }
}
