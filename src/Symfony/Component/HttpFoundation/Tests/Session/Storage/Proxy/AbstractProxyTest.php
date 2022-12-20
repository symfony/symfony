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
use Symfony\Component\HttpFoundation\Session\Storage\Proxy\AbstractProxy;
use Symfony\Component\HttpFoundation\Session\Storage\Proxy\SessionHandlerProxy;

/**
 * Test class for AbstractProxy.
 *
 * @author Drak <drak@zikula.org>
 */
class AbstractProxyTest extends TestCase
{
    /**
     * @var AbstractProxy
     */
    protected $proxy;

    protected function setUp(): void
    {
        $this->proxy = self::getMockForAbstractClass(AbstractProxy::class);
    }

    protected function tearDown(): void
    {
        $this->proxy = null;
    }

    public function testGetSaveHandlerName()
    {
        self::assertNull($this->proxy->getSaveHandlerName());
    }

    public function testIsSessionHandlerInterface()
    {
        self::assertFalse($this->proxy->isSessionHandlerInterface());
        $sh = new SessionHandlerProxy(new \SessionHandler());
        self::assertTrue($sh->isSessionHandlerInterface());
    }

    public function testIsWrapper()
    {
        self::assertFalse($this->proxy->isWrapper());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testIsActive()
    {
        self::assertFalse($this->proxy->isActive());
        session_start();
        self::assertTrue($this->proxy->isActive());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testName()
    {
        self::assertEquals(session_name(), $this->proxy->getName());
        $this->proxy->setName('foo');
        self::assertEquals('foo', $this->proxy->getName());
        self::assertEquals(session_name(), $this->proxy->getName());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testNameException()
    {
        self::expectException(\LogicException::class);
        session_start();
        $this->proxy->setName('foo');
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testId()
    {
        self::assertEquals(session_id(), $this->proxy->getId());
        $this->proxy->setId('foo');
        self::assertEquals('foo', $this->proxy->getId());
        self::assertEquals(session_id(), $this->proxy->getId());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testIdException()
    {
        self::expectException(\LogicException::class);
        session_start();
        $this->proxy->setId('foo');
    }
}
