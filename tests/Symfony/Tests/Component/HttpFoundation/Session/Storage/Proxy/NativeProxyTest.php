<?php

namespace Symfony\Tests\Component\HttpFoundation\Session\Storage\Proxy;

use Symfony\Component\HttpFoundation\Session\Storage\Proxy\NativeProxy;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NativeFileSessionHandler;

/**
 * Test class for NativeProxy.
 *
 * @author Drak <drak@zikula.org>
 */
class NativeProxyTest extends \PHPUnit_Framework_TestCase
{
    public function testIsWrapper()
    {
        $proxy = new NativeProxy();
        $this->assertFalse($proxy->isWrapper());
    }

    public function testGetSaveHandlerName()
    {
        $name = ini_get('session.save_handler');
        $proxy = new NativeProxy();
        $this->assertEquals($name, $proxy->getSaveHandlerName());
    }
}
