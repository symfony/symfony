<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Csrf\Tests\TokenStorage;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class SessionTokenStorageTest extends TestCase
{
    const SESSION_NAMESPACE = 'foobar';

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $session;

    /**
     * @var SessionTokenStorage
     */
    private $storage;

    protected function setUp(): void
    {
        $this->session = $this->getMockBuilder('Symfony\Component\HttpFoundation\Session\SessionInterface')
            ->disableOriginalConstructor()
            ->getMock();
        $this->storage = new SessionTokenStorage($this->session, self::SESSION_NAMESPACE);
    }

    public function testStoreTokenInClosedSession(): void
    {
        $this->session->expects($this->any())
            ->method('isStarted')
            ->will($this->returnValue(false));

        $this->session->expects($this->once())
            ->method('start');

        $this->session->expects($this->once())
            ->method('set')
            ->with(self::SESSION_NAMESPACE.'/token_id', 'TOKEN');

        $this->storage->setToken('token_id', 'TOKEN');
    }

    public function testStoreTokenInActiveSession(): void
    {
        $this->session->expects($this->any())
            ->method('isStarted')
            ->will($this->returnValue(true));

        $this->session->expects($this->never())
            ->method('start');

        $this->session->expects($this->once())
            ->method('set')
            ->with(self::SESSION_NAMESPACE.'/token_id', 'TOKEN');

        $this->storage->setToken('token_id', 'TOKEN');
    }

    public function testCheckTokenInClosedSession(): void
    {
        $this->session->expects($this->any())
            ->method('isStarted')
            ->will($this->returnValue(false));

        $this->session->expects($this->once())
            ->method('start');

        $this->session->expects($this->once())
            ->method('has')
            ->with(self::SESSION_NAMESPACE.'/token_id')
            ->will($this->returnValue('RESULT'));

        $this->assertSame('RESULT', $this->storage->hasToken('token_id'));
    }

    public function testCheckTokenInActiveSession(): void
    {
        $this->session->expects($this->any())
            ->method('isStarted')
            ->will($this->returnValue(true));

        $this->session->expects($this->never())
            ->method('start');

        $this->session->expects($this->once())
            ->method('has')
            ->with(self::SESSION_NAMESPACE.'/token_id')
            ->will($this->returnValue('RESULT'));

        $this->assertSame('RESULT', $this->storage->hasToken('token_id'));
    }

    public function testGetExistingTokenFromClosedSession(): void
    {
        $this->session->expects($this->any())
            ->method('isStarted')
            ->will($this->returnValue(false));

        $this->session->expects($this->once())
            ->method('start');

        $this->session->expects($this->once())
            ->method('has')
            ->with(self::SESSION_NAMESPACE.'/token_id')
            ->will($this->returnValue(true));

        $this->session->expects($this->once())
            ->method('get')
            ->with(self::SESSION_NAMESPACE.'/token_id')
            ->will($this->returnValue('RESULT'));

        $this->assertSame('RESULT', $this->storage->getToken('token_id'));
    }

    public function testGetExistingTokenFromActiveSession(): void
    {
        $this->session->expects($this->any())
            ->method('isStarted')
            ->will($this->returnValue(true));

        $this->session->expects($this->never())
            ->method('start');

        $this->session->expects($this->once())
            ->method('has')
            ->with(self::SESSION_NAMESPACE.'/token_id')
            ->will($this->returnValue(true));

        $this->session->expects($this->once())
            ->method('get')
            ->with(self::SESSION_NAMESPACE.'/token_id')
            ->will($this->returnValue('RESULT'));

        $this->assertSame('RESULT', $this->storage->getToken('token_id'));
    }

    /**
     * @expectedException \Symfony\Component\Security\Csrf\Exception\TokenNotFoundException
     */
    public function testGetNonExistingTokenFromClosedSession(): void
    {
        $this->session->expects($this->any())
            ->method('isStarted')
            ->will($this->returnValue(false));

        $this->session->expects($this->once())
            ->method('start');

        $this->session->expects($this->once())
            ->method('has')
            ->with(self::SESSION_NAMESPACE.'/token_id')
            ->will($this->returnValue(false));

        $this->storage->getToken('token_id');
    }

    /**
     * @expectedException \Symfony\Component\Security\Csrf\Exception\TokenNotFoundException
     */
    public function testGetNonExistingTokenFromActiveSession(): void
    {
        $this->session->expects($this->any())
            ->method('isStarted')
            ->will($this->returnValue(true));

        $this->session->expects($this->never())
            ->method('start');

        $this->session->expects($this->once())
            ->method('has')
            ->with(self::SESSION_NAMESPACE.'/token_id')
            ->will($this->returnValue(false));

        $this->storage->getToken('token_id');
    }

    public function testRemoveNonExistingTokenFromClosedSession(): void
    {
        $this->session->expects($this->any())
            ->method('isStarted')
            ->will($this->returnValue(false));

        $this->session->expects($this->once())
            ->method('start');

        $this->session->expects($this->once())
            ->method('remove')
            ->with(self::SESSION_NAMESPACE.'/token_id')
            ->will($this->returnValue(null));

        $this->assertNull($this->storage->removeToken('token_id'));
    }

    public function testRemoveNonExistingTokenFromActiveSession(): void
    {
        $this->session->expects($this->any())
            ->method('isStarted')
            ->will($this->returnValue(true));

        $this->session->expects($this->never())
            ->method('start');

        $this->session->expects($this->once())
            ->method('remove')
            ->with(self::SESSION_NAMESPACE.'/token_id')
            ->will($this->returnValue(null));

        $this->assertNull($this->storage->removeToken('token_id'));
    }

    public function testRemoveExistingTokenFromClosedSession(): void
    {
        $this->session->expects($this->any())
            ->method('isStarted')
            ->will($this->returnValue(false));

        $this->session->expects($this->once())
            ->method('start');

        $this->session->expects($this->once())
            ->method('remove')
            ->with(self::SESSION_NAMESPACE.'/token_id')
            ->will($this->returnValue('TOKEN'));

        $this->assertSame('TOKEN', $this->storage->removeToken('token_id'));
    }

    public function testRemoveExistingTokenFromActiveSession(): void
    {
        $this->session->expects($this->any())
            ->method('isStarted')
            ->will($this->returnValue(true));

        $this->session->expects($this->never())
            ->method('start');

        $this->session->expects($this->once())
            ->method('remove')
            ->with(self::SESSION_NAMESPACE.'/token_id')
            ->will($this->returnValue('TOKEN'));

        $this->assertSame('TOKEN', $this->storage->removeToken('token_id'));
    }
}
