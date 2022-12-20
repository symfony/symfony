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
use Symfony\Component\Security\Core\Authentication\Provider\RememberMeAuthenticationProvider;
use Symfony\Component\Security\Core\Authentication\Token\RememberMeToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\DisabledException;
use Symfony\Component\Security\Core\Exception\LogicException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @group legacy
 */
class RememberMeAuthenticationProviderTest extends TestCase
{
    public function testSupports()
    {
        $provider = $this->getProvider();

        self::assertTrue($provider->supports($this->getSupportedToken()));
        self::assertFalse($provider->supports(self::createMock(TokenInterface::class)));
        self::assertFalse($provider->supports(self::createMock(RememberMeToken::class)));
    }

    public function testAuthenticateWhenTokenIsNotSupported()
    {
        self::expectException(AuthenticationException::class);
        self::expectExceptionMessage('The token is not supported by this authentication provider.');
        $provider = $this->getProvider();

        $token = self::createMock(TokenInterface::class);
        $provider->authenticate($token);
    }

    public function testAuthenticateWhenSecretsDoNotMatch()
    {
        self::expectException(BadCredentialsException::class);
        $provider = $this->getProvider(null, 'secret1');
        $token = $this->getSupportedToken(null, 'secret2');

        $provider->authenticate($token);
    }

    public function testAuthenticateThrowsOnNonUserInterfaceInstance()
    {
        self::expectException(LogicException::class);
        self::expectExceptionMessage('Method "Symfony\Component\Security\Core\Authentication\Token\RememberMeToken::getUser()" must return a "Symfony\Component\Security\Core\User\UserInterface" instance, "string" returned.');

        $provider = $this->getProvider();
        $token = new RememberMeToken(new InMemoryUser('dummyuser', null), 'foo', 'test');
        $token->setUser('stringish-user');
        $provider->authenticate($token);
    }

    public function testAuthenticateWhenPreChecksFails()
    {
        self::expectException(DisabledException::class);
        $userChecker = self::createMock(UserCheckerInterface::class);
        $userChecker->expects(self::once())
            ->method('checkPreAuth')
            ->willThrowException(new DisabledException());

        $provider = $this->getProvider($userChecker);

        $provider->authenticate($this->getSupportedToken());
    }

    public function testAuthenticate()
    {
        $user = self::createMock(UserInterface::class);
        $user->expects(self::exactly(2))
             ->method('getRoles')
             ->willReturn(['ROLE_FOO']);

        $provider = $this->getProvider();

        $token = $this->getSupportedToken($user);
        $authToken = $provider->authenticate($token);

        self::assertInstanceOf(RememberMeToken::class, $authToken);
        self::assertSame($user, $authToken->getUser());
        self::assertEquals(['ROLE_FOO'], $authToken->getRoleNames());
        self::assertEquals('', $authToken->getCredentials());
    }

    protected function getSupportedToken($user = null, $secret = 'test')
    {
        if (null === $user) {
            $user = self::createMock(UserInterface::class);
            $user
                ->expects(self::any())
                ->method('getRoles')
                ->willReturn([]);
        }

        $token = self::getMockBuilder(RememberMeToken::class)->setMethods(['getFirewallName'])->setConstructorArgs([$user, 'foo', $secret])->getMock();
        $token
            ->expects(self::once())
            ->method('getFirewallName')
            ->willReturn('foo');

        return $token;
    }

    protected function getProvider($userChecker = null, $key = 'test')
    {
        if (null === $userChecker) {
            $userChecker = self::createMock(UserCheckerInterface::class);
        }

        return new RememberMeAuthenticationProvider($userChecker, $key, 'foo');
    }
}
