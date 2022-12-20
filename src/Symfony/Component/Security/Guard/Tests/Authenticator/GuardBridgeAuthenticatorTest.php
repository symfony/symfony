<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Guard\Tests\Authenticator;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\Authenticator\GuardBridgeAuthenticator;
use Symfony\Component\Security\Guard\AuthenticatorInterface;
use Symfony\Component\Security\Guard\Token\PostAuthenticationGuardToken;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * @group legacy
 */
class GuardBridgeAuthenticatorTest extends TestCase
{
    private $guardAuthenticator;
    private $userProvider;
    private $authenticator;

    protected function setUp(): void
    {
        if (!interface_exists(\Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface::class)) {
            self::markTestSkipped('Authenticator system not installed.');
        }

        $this->guardAuthenticator = self::createMock(AuthenticatorInterface::class);
        $this->userProvider = self::createMock(UserProviderInterface::class);
        $this->authenticator = new GuardBridgeAuthenticator($this->guardAuthenticator, $this->userProvider);
    }

    public function testSupports()
    {
        $request = new Request();

        $this->guardAuthenticator->expects(self::once())
            ->method('supports')
            ->with($request)
            ->willReturn(true);

        self::assertTrue($this->authenticator->supports($request));
    }

    public function testNoSupport()
    {
        $request = new Request();

        $this->guardAuthenticator->expects(self::once())
            ->method('supports')
            ->with($request)
            ->willReturn(false);

        self::assertFalse($this->authenticator->supports($request));
    }

    public function testAuthenticate()
    {
        $request = new Request();

        $credentials = ['password' => 's3cr3t'];
        $this->guardAuthenticator->expects(self::once())
            ->method('getCredentials')
            ->with($request)
            ->willReturn($credentials);

        $user = new InMemoryUser('test', null, ['ROLE_USER']);
        $this->guardAuthenticator->expects(self::once())
            ->method('getUser')
            ->with($credentials, $this->userProvider)
            ->willReturn($user);

        $passport = $this->authenticator->authenticate($request);
        self::assertEquals($user, $passport->getUser());
        self::assertTrue($passport->hasBadge(CustomCredentials::class));

        $this->guardAuthenticator->expects(self::once())
            ->method('checkCredentials')
            ->with($credentials, $user)
            ->willReturn(true);

        $passport->getBadge(CustomCredentials::class)->executeCustomChecker($user);
    }

    public function testAuthenticateNoUser()
    {
        self::expectException(UserNotFoundException::class);

        $request = new Request();

        $credentials = ['password' => 's3cr3t'];
        $this->guardAuthenticator->expects(self::once())
            ->method('getCredentials')
            ->with($request)
            ->willReturn($credentials);

        $this->guardAuthenticator->expects(self::once())
            ->method('getUser')
            ->with($credentials, $this->userProvider)
            ->willReturn(null);

        $passport = $this->authenticator->authenticate($request);
        $passport->getUser();
    }

    /**
     * @dataProvider provideRememberMeData
     */
    public function testAuthenticateRememberMe(bool $rememberMeSupported)
    {
        $request = new Request();

        $credentials = ['password' => 's3cr3t'];
        $this->guardAuthenticator->expects(self::once())
            ->method('getCredentials')
            ->with($request)
            ->willReturn($credentials);

        $this->guardAuthenticator->expects(self::once())
            ->method('supportsRememberMe')
            ->willReturn($rememberMeSupported);

        $passport = $this->authenticator->authenticate($request);
        self::assertEquals($rememberMeSupported, $passport->hasBadge(RememberMeBadge::class));
    }

    public function provideRememberMeData()
    {
        yield [true];
        yield [false];
    }

    public function testCreateAuthenticatedToken()
    {
        $user = new InMemoryUser('test', null, ['ROLE_USER']);

        $token = new PostAuthenticationGuardToken($user, 'main', ['ROLE_USER']);
        $this->guardAuthenticator->expects(self::once())
            ->method('createAuthenticatedToken')
            ->with($user, 'main')
            ->willReturn($token);

        self::assertSame($token, $this->authenticator->createAuthenticatedToken(new SelfValidatingPassport(new UserBadge('test', function () use ($user) { return $user; })), 'main'));
    }

    public function testHandleSuccess()
    {
        $request = new Request();
        $token = new PostAuthenticationGuardToken(new InMemoryUser('test', null, ['ROLE_USER']), 'main', ['ROLE_USER']);

        $response = new Response();
        $this->guardAuthenticator->expects(self::once())
            ->method('onAuthenticationSuccess')
            ->with($request, $token)
            ->willReturn($response);

        self::assertSame($response, $this->authenticator->onAuthenticationSuccess($request, $token, 'main'));
    }

    public function testOnFailure()
    {
        $request = new Request();
        $exception = new AuthenticationException();

        $response = new Response();
        $this->guardAuthenticator->expects(self::once())
            ->method('onAuthenticationFailure')
            ->with($request, $exception)
            ->willReturn($response);

        self::assertSame($response, $this->authenticator->onAuthenticationFailure($request, $exception));
    }
}
