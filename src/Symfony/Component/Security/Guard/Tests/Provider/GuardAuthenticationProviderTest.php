<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Guard\Tests\Provider;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\AuthenticationExpiredException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\AuthenticatorInterface;
use Symfony\Component\Security\Guard\Provider\GuardAuthenticationProvider;
use Symfony\Component\Security\Guard\Token\GuardTokenInterface;
use Symfony\Component\Security\Guard\Token\PostAuthenticationGuardToken;
use Symfony\Component\Security\Guard\Token\PreAuthenticationGuardToken;

/**
 * @author Ryan Weaver <weaverryan@gmail.com>
 * @group legacy
 */
class GuardAuthenticationProviderTest extends TestCase
{
    private $userProvider;
    private $userChecker;
    private $preAuthenticationToken;

    public function testAuthenticate()
    {
        $providerKey = 'my_cool_firewall';

        $authenticatorA = self::createMock(AuthenticatorInterface::class);
        $authenticatorB = self::createMock(AuthenticatorInterface::class);
        $authenticatorC = self::createMock(AuthenticatorInterface::class);
        $authenticators = [$authenticatorA, $authenticatorB, $authenticatorC];

        // called 2 times - for authenticator A and B (stops on B because of match)
        $this->preAuthenticationToken->expects(self::exactly(2))
            ->method('getGuardProviderKey')
            // it will return the "1" index, which will match authenticatorB
            ->willReturn('my_cool_firewall_1');

        $enteredCredentials = [
            'username' => '_weaverryan_test_user',
            'password' => 'guard_auth_ftw',
        ];
        $this->preAuthenticationToken->expects(self::atLeastOnce())
            ->method('getCredentials')
            ->willReturn($enteredCredentials);

        // authenticators A and C are never called
        $authenticatorA->expects(self::never())
            ->method('getUser');
        $authenticatorC->expects(self::never())
            ->method('getUser');

        $mockedUser = self::createMock(UserInterface::class);
        $authenticatorB->expects(self::once())
            ->method('getUser')
            ->with($enteredCredentials, $this->userProvider)
            ->willReturn($mockedUser);
        // checkCredentials is called
        $authenticatorB->expects(self::once())
            ->method('checkCredentials')
            ->with($enteredCredentials, $mockedUser)
            // authentication works!
            ->willReturn(true);
        $authedToken = self::createMock(GuardTokenInterface::class);
        $authenticatorB->expects(self::once())
            ->method('createAuthenticatedToken')
            ->with($mockedUser, $providerKey)
            ->willReturn($authedToken);

        // user checker should be called
        $this->userChecker->expects(self::once())
            ->method('checkPreAuth')
            ->with($mockedUser);
        $this->userChecker->expects(self::once())
            ->method('checkPostAuth')
            ->with($mockedUser);

        $provider = new GuardAuthenticationProvider($authenticators, $this->userProvider, $providerKey, $this->userChecker);
        $actualAuthedToken = $provider->authenticate($this->preAuthenticationToken);
        self::assertSame($authedToken, $actualAuthedToken);
    }

    public function testCheckCredentialsReturningFalseFailsAuthentication()
    {
        self::expectException(BadCredentialsException::class);
        $providerKey = 'my_uncool_firewall';

        $authenticator = self::createMock(AuthenticatorInterface::class);

        // make sure the authenticator is used
        $this->preAuthenticationToken->expects(self::any())
            ->method('getGuardProviderKey')
            // the 0 index, to match the only authenticator
            ->willReturn('my_uncool_firewall_0');

        $this->preAuthenticationToken->expects(self::atLeastOnce())
            ->method('getCredentials')
            ->willReturn('non-null-value');

        $mockedUser = self::createMock(UserInterface::class);
        $authenticator->expects(self::once())
            ->method('getUser')
            ->willReturn($mockedUser);
        // checkCredentials is called
        $authenticator->expects(self::once())
            ->method('checkCredentials')
            // authentication fails :(
            ->willReturn(false);

        $provider = new GuardAuthenticationProvider([$authenticator], $this->userProvider, $providerKey, $this->userChecker);
        $provider->authenticate($this->preAuthenticationToken);
    }

    public function testGuardWithNoLongerAuthenticatedTriggersLogout()
    {
        self::expectException(AuthenticationExpiredException::class);
        $providerKey = 'my_firewall_abc';

        // create a token and mark it as NOT authenticated anymore
        // this mimics what would happen if a user "changed" between request
        $mockedUser = self::createMock(UserInterface::class);
        $token = new PostAuthenticationGuardToken($mockedUser, $providerKey, ['ROLE_USER']);
        $token->setAuthenticated(false);

        $provider = new GuardAuthenticationProvider([], $this->userProvider, $providerKey, $this->userChecker);
        $provider->authenticate($token);
    }

    public function testSupportsChecksGuardAuthenticatorsTokenOrigin()
    {
        $authenticatorA = self::createMock(AuthenticatorInterface::class);
        $authenticatorB = self::createMock(AuthenticatorInterface::class);
        $authenticators = [$authenticatorA, $authenticatorB];

        $mockedUser = self::createMock(UserInterface::class);
        $provider = new GuardAuthenticationProvider($authenticators, $this->userProvider, 'first_firewall', $this->userChecker);

        $token = new PreAuthenticationGuardToken($mockedUser, 'first_firewall_1');
        $supports = $provider->supports($token);
        self::assertTrue($supports);

        $token = new PreAuthenticationGuardToken($mockedUser, 'second_firewall_0');
        $supports = $provider->supports($token);
        self::assertFalse($supports);
    }

    public function testAuthenticateFailsOnNonOriginatingToken()
    {
        self::expectException(AuthenticationException::class);
        self::expectExceptionMessageMatches('/second_firewall_0/');
        $authenticatorA = self::createMock(AuthenticatorInterface::class);
        $authenticators = [$authenticatorA];

        $mockedUser = self::createMock(UserInterface::class);
        $provider = new GuardAuthenticationProvider($authenticators, $this->userProvider, 'first_firewall', $this->userChecker);

        $token = new PreAuthenticationGuardToken($mockedUser, 'second_firewall_0');
        $provider->authenticate($token);
    }

    protected function setUp(): void
    {
        $this->userProvider = self::createMock(UserProviderInterface::class);
        $this->userChecker = self::createMock(UserCheckerInterface::class);
        $this->preAuthenticationToken = self::createMock(PreAuthenticationGuardToken::class);
    }

    protected function tearDown(): void
    {
        $this->userProvider = null;
        $this->userChecker = null;
        $this->preAuthenticationToken = null;
    }
}
