<?php

namespace Symfony\Tests\Component\HttpFoundation\Session\Storage\Proxy;

use Symfony\Component\HttpFoundation\Session\Storage\Proxy\SessionHandlerProxy;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\NullSessionHandler;

/**
 * @runTestsInSeparateProcesses
 */
class SessionHandlerProxyTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var SessionHandlerProxy
     */
    protected $proxy;

    protected function setUp()
    {
        $this->proxy = new SessionHandlerProxy(new NullSessionHandler());
    }

    protected function tearDown()
    {
        $this->proxy = null;
    }

    public function testOpen()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    public function testClose()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    public function testRead()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    public function testWrite()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    public function testDestroy()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    public function testGc()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

}
