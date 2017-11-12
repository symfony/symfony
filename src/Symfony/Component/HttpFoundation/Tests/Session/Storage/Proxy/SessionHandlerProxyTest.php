<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpFoundation\Tests\Session\Storage\Proxy;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Storage\Proxy\SessionHandlerProxy;

/**
 * Tests for SessionHandlerProxy class.
 *
 * @author Drak <drak@zikula.org>
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class SessionHandlerProxyTest extends TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_Matcher
     */
    private $mock;

    /**
     * @var SessionHandlerProxy
     */
    private $proxy;

    protected function setUp(): void
    {
        $this->mock = $this->getMockBuilder('SessionHandlerInterface')->getMock();
        $this->proxy = new SessionHandlerProxy($this->mock);
    }

    protected function tearDown(): void
    {
        $this->mock = null;
        $this->proxy = null;
    }

    public function testOpenTrue(): void
    {
        $this->mock->expects($this->once())
            ->method('open')
            ->will($this->returnValue(true));

        $this->assertFalse($this->proxy->isActive());
        $this->proxy->open('name', 'id');
        $this->assertFalse($this->proxy->isActive());
    }

    public function testOpenFalse(): void
    {
        $this->mock->expects($this->once())
            ->method('open')
            ->will($this->returnValue(false));

        $this->assertFalse($this->proxy->isActive());
        $this->proxy->open('name', 'id');
        $this->assertFalse($this->proxy->isActive());
    }

    public function testClose(): void
    {
        $this->mock->expects($this->once())
            ->method('close')
            ->will($this->returnValue(true));

        $this->assertFalse($this->proxy->isActive());
        $this->proxy->close();
        $this->assertFalse($this->proxy->isActive());
    }

    public function testCloseFalse(): void
    {
        $this->mock->expects($this->once())
            ->method('close')
            ->will($this->returnValue(false));

        $this->assertFalse($this->proxy->isActive());
        $this->proxy->close();
        $this->assertFalse($this->proxy->isActive());
    }

    public function testRead(): void
    {
        $this->mock->expects($this->once())
            ->method('read');

        $this->proxy->read('id');
    }

    public function testWrite(): void
    {
        $this->mock->expects($this->once())
            ->method('write');

        $this->proxy->write('id', 'data');
    }

    public function testDestroy(): void
    {
        $this->mock->expects($this->once())
            ->method('destroy');

        $this->proxy->destroy('id');
    }

    public function testGc(): void
    {
        $this->mock->expects($this->once())
            ->method('gc');

        $this->proxy->gc(86400);
    }
}
