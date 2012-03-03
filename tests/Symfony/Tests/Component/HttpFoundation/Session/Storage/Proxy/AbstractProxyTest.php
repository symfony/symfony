<?php

namespace Symfony\Tests\Component\HttpFoundation\Session\Storage\Proxy;

use Symfony\Component\HttpFoundation\Session\Storage\Proxy\AbstractProxy;

class ConcreteProxy extends AbstractProxy
{

}

/**
 * Test class for AbstractProxy.
 *
 * @runTestsInSeparateProcesses
 */
class AbstractProxyTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var AbstractProxy
     */
    protected $proxy;

    protected function setUp()
    {
        $this->proxy = new ConcreteProxy;
    }

    protected function tearDown()
    {
        $this->proxy = null;
    }

    public function testGetSaveHandlerName()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    public function testIsSessionHandlerInterface()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    public function testIsWrapper()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    public function testIsActive()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

    public function testSetActive()
    {
        $this->markTestIncomplete(
            'This test has not been implemented yet.'
        );
    }

}
