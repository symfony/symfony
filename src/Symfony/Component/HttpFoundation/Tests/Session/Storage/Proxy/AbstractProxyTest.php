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

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
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
    protected AbstractProxy $proxy;

    protected function setUp(): void
    {
        $this->proxy = new class extends AbstractProxy {};
    }

    public function testGetSaveHandlerName(): void
    {
        $this->assertNull($this->proxy->getSaveHandlerName());
    }

    public function testIsSessionHandlerInterface(): void
    {
        $this->assertFalse($this->proxy->isSessionHandlerInterface());
        $sh = new SessionHandlerProxy(new \SessionHandler());
        $this->assertTrue($sh->isSessionHandlerInterface());
    }

    public function testIsWrapper(): void
    {
        $this->assertFalse($this->proxy->isWrapper());
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function testIsActive(): void
    {
        $this->assertFalse($this->proxy->isActive());
        session_start();
        $this->assertTrue($this->proxy->isActive());
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function testName(): void
    {
        $this->assertEquals(session_name(), $this->proxy->getName());
        $this->proxy->setName('foo');
        $this->assertEquals('foo', $this->proxy->getName());
        $this->assertEquals(session_name(), $this->proxy->getName());
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function testNameException(): void
    {
        $this->expectException(\LogicException::class);
        session_start();
        $this->proxy->setName('foo');
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function testId(): void
    {
        $this->assertEquals(session_id(), $this->proxy->getId());
        $this->proxy->setId('foo');
        $this->assertEquals('foo', $this->proxy->getId());
        $this->assertEquals(session_id(), $this->proxy->getId());
    }

    #[PreserveGlobalState(false)]
    #[RunInSeparateProcess]
    public function testIdException(): void
    {
        $this->expectException(\LogicException::class);
        session_start();
        $this->proxy->setId('foo');
    }
}
