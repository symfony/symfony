<?php

namespace Symfony\Tests\Component\HttpFoundation\Session\Storage\Proxy;

use Symfony\Component\HttpFoundation\Session\Storage\Proxy\NativeProxy;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeFileSessionHandler;

/**
 * Test class for NativeProxy.
 *
 * @runTestsInSeparateProcesses
 */
class NativeProxyPHP54Test extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        if (version_compare(phpversion(), '5.4.0', '<')) {
            $this->markTestSkipped('Test skipped, only for PHP 5.4');
        }
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testConstructor()
    {
        $proxy = new NativeProxy(new NativeFileSessionHandler());
        $this->assertTrue($proxy->isWrapper());
    }
}
