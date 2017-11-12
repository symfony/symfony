<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Csrf\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManager;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class CsrfTokenManagerTest extends TestCase
{
    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $generator;

    /**
     * @var \PHPUnit_Framework_MockObject_MockObject
     */
    private $storage;

    /**
     * @var CsrfTokenManager
     */
    private $manager;

    protected function setUp(): void
    {
        $this->generator = $this->getMockBuilder('Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface')->getMock();
        $this->storage = $this->getMockBuilder('Symfony\Component\Security\Csrf\TokenStorage\TokenStorageInterface')->getMock();
        $this->manager = new CsrfTokenManager($this->generator, $this->storage);
    }

    protected function tearDown(): void
    {
        $this->generator = null;
        $this->storage = null;
        $this->manager = null;
    }

    public function testGetNonExistingToken(): void
    {
        $this->storage->expects($this->once())
            ->method('hasToken')
            ->with('token_id')
            ->will($this->returnValue(false));

        $this->generator->expects($this->once())
            ->method('generateToken')
            ->will($this->returnValue('TOKEN'));

        $this->storage->expects($this->once())
            ->method('setToken')
            ->with('token_id', 'TOKEN');

        $token = $this->manager->getToken('token_id');

        $this->assertInstanceOf('Symfony\Component\Security\Csrf\CsrfToken', $token);
        $this->assertSame('token_id', $token->getId());
        $this->assertSame('TOKEN', $token->getValue());
    }

    public function testUseExistingTokenIfAvailable(): void
    {
        $this->storage->expects($this->once())
            ->method('hasToken')
            ->with('token_id')
            ->will($this->returnValue(true));

        $this->storage->expects($this->once())
            ->method('getToken')
            ->with('token_id')
            ->will($this->returnValue('TOKEN'));

        $token = $this->manager->getToken('token_id');

        $this->assertInstanceOf('Symfony\Component\Security\Csrf\CsrfToken', $token);
        $this->assertSame('token_id', $token->getId());
        $this->assertSame('TOKEN', $token->getValue());
    }

    public function testRefreshTokenAlwaysReturnsNewToken(): void
    {
        $this->storage->expects($this->never())
            ->method('hasToken');

        $this->generator->expects($this->once())
            ->method('generateToken')
            ->will($this->returnValue('TOKEN'));

        $this->storage->expects($this->once())
            ->method('setToken')
            ->with('token_id', 'TOKEN');

        $token = $this->manager->refreshToken('token_id');

        $this->assertInstanceOf('Symfony\Component\Security\Csrf\CsrfToken', $token);
        $this->assertSame('token_id', $token->getId());
        $this->assertSame('TOKEN', $token->getValue());
    }

    public function testMatchingTokenIsValid(): void
    {
        $this->storage->expects($this->once())
            ->method('hasToken')
            ->with('token_id')
            ->will($this->returnValue(true));

        $this->storage->expects($this->once())
            ->method('getToken')
            ->with('token_id')
            ->will($this->returnValue('TOKEN'));

        $this->assertTrue($this->manager->isTokenValid(new CsrfToken('token_id', 'TOKEN')));
    }

    public function testNonMatchingTokenIsNotValid(): void
    {
        $this->storage->expects($this->once())
            ->method('hasToken')
            ->with('token_id')
            ->will($this->returnValue(true));

        $this->storage->expects($this->once())
            ->method('getToken')
            ->with('token_id')
            ->will($this->returnValue('TOKEN'));

        $this->assertFalse($this->manager->isTokenValid(new CsrfToken('token_id', 'FOOBAR')));
    }

    public function testNonExistingTokenIsNotValid(): void
    {
        $this->storage->expects($this->once())
            ->method('hasToken')
            ->with('token_id')
            ->will($this->returnValue(false));

        $this->storage->expects($this->never())
            ->method('getToken');

        $this->assertFalse($this->manager->isTokenValid(new CsrfToken('token_id', 'FOOBAR')));
    }

    public function testRemoveToken(): void
    {
        $this->storage->expects($this->once())
            ->method('removeToken')
            ->with('token_id')
            ->will($this->returnValue('REMOVED_TOKEN'));

        $this->assertSame('REMOVED_TOKEN', $this->manager->removeToken('token_id'));
    }
}
