<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Core\Tests\Authentication\Provider;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Provider\SimpleAuthenticationProvider;
use Symfony\Component\Security\Core\Authentication\SimpleAuthenticatorInterface;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\Exception\LockedException;
use Symfony\Component\Security\Core\Tests\Fixtures\TokenInterface;
use Symfony\Component\Security\Core\User\UserChecker;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * @group legacy
 */
class SimpleAuthenticationProviderTest extends TestCase
{
    public function testAuthenticateWhenPreChecksFails()
    {
        $this->expectException(DisabledException::class);
        $user = $this->createMock(UserInterface::class);

        $token = $this->createMock(TokenInterface::class);
        $token->expects($this->any())
            ->method('getUser')
            ->willReturn($user);

        $userChecker = $this->createMock(UserCheckerInterface::class);
        $userChecker->expects($this->once())
            ->method('checkPreAuth')
            ->willThrowException(new DisabledException());

        $authenticator = $this->createMock(SimpleAuthenticatorInterface::class);
        $authenticator->expects($this->once())
            ->method('authenticateToken')
            ->willReturn($token);

        $provider = $this->getProvider($authenticator, null, $userChecker);

        $provider->authenticate($token);
    }

    public function testAuthenticateWhenPostChecksFails()
    {
        $this->expectException(LockedException::class);
        $user = $this->createMock(UserInterface::class);

        $token = $this->createMock(TokenInterface::class);
        $token->expects($this->any())
            ->method('getUser')
            ->willReturn($user);

        $userChecker = $this->createMock(UserCheckerInterface::class);
        $userChecker->expects($this->once())
            ->method('checkPostAuth')
            ->willThrowException(new LockedException());

        $authenticator = $this->createMock(SimpleAuthenticatorInterface::class);
        $authenticator->expects($this->once())
            ->method('authenticateToken')
            ->willReturn($token);

        $provider = $this->getProvider($authenticator, null, $userChecker);

        $provider->authenticate($token);
    }

    public function testAuthenticateSkipsUserChecksForNonUserInterfaceObjects()
    {
        $token = $this->createMock(TokenInterface::class);
        $token->expects($this->any())
            ->method('getUser')
            ->willReturn('string-user');
        $authenticator = $this->createMock(SimpleAuthenticatorInterface::class);
        $authenticator->expects($this->once())
            ->method('authenticateToken')
            ->willReturn($token);

        $this->assertSame($token, $this->getProvider($authenticator, null, new UserChecker())->authenticate($token));
    }

    protected function getProvider($simpleAuthenticator = null, $userProvider = null, $userChecker = null, $key = 'test')
    {
        if (null === $userChecker) {
            $userChecker = $this->createMock(UserCheckerInterface::class);
        }
        if (null === $simpleAuthenticator) {
            $simpleAuthenticator = $this->createMock(SimpleAuthenticatorInterface::class);
        }
        if (null === $userProvider) {
            $userProvider = $this->createMock(UserProviderInterface::class);
        }

        return new SimpleAuthenticationProvider($simpleAuthenticator, $userProvider, $key, $userChecker);
    }
}
