<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Extension\Csrf\CsrfProvider;

use Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class SessionTokenStorageTest extends \PHPUnit_Framework_TestCase
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

    protected function setUp()
    {
        if (!class_exists('Symfony\Component\HttpFoundation\Session\SessionInterface')) {
            $this->markTestSkipped('The "HttpFoundation" component is not available');
        }

        $this->session = $this->getMockBuilder('Symfony\Component\HttpFoundation\Session\SessionInterface')
            ->disableOriginalConstructor()
            ->getMock();
        $this->storage = new SessionTokenStorage($this->session, self::SESSION_NAMESPACE);
    }

    public function testStoreTokenInClosedSession()
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

    public function testStoreTokenInActiveSession()
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

    public function testCheckTokenInClosedSession()
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

    public function testCheckTokenInActiveSession()
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

    public function testGetTokenFromClosedSession()
    {
        $this->session->expects($this->any())
            ->method('isStarted')
            ->will($this->returnValue(false));

        $this->session->expects($this->once())
            ->method('start');

        $this->session->expects($this->once())
            ->method('get')
            ->with(self::SESSION_NAMESPACE.'/token_id', 'DEFAULT')
            ->will($this->returnValue('RESULT'));

        $this->assertSame('RESULT', $this->storage->getToken('token_id', 'DEFAULT'));
    }

    public function testGetTokenFromActiveSession()
    {
        $this->session->expects($this->any())
            ->method('isStarted')
            ->will($this->returnValue(true));

        $this->session->expects($this->never())
            ->method('start');

        $this->session->expects($this->once())
            ->method('get')
            ->with(self::SESSION_NAMESPACE.'/token_id', 'DEFAULT')
            ->will($this->returnValue('RESULT'));

        $this->assertSame('RESULT', $this->storage->getToken('token_id', 'DEFAULT'));
    }
}
