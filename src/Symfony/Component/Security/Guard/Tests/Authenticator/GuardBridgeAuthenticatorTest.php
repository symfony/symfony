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
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\Authenticator\GuardBridgeAuthenticator;
use Symfony\Component\Security\Guard\AuthenticatorInterface;
use Symfony\Component\Security\Guard\Token\PostAuthenticationGuardToken;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class GuardBridgeAuthenticatorTest extends TestCase
{
    private $guardAuthenticator;
    private $userProvider;
    private $authenticator;

    protected function setUp(): void
    {
        if (!interface_exists(\Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface::class)) {
            $this->markTestSkipped('Authenticator system not installed.');
        }

        $this->guardAuthenticator = $this->createMock(AuthenticatorInterface::class);
        $this->userProvider = $this->createMock(UserProviderInterface::class);
        $this->authenticator = new GuardBridgeAuthenticator($this->guardAuthenticator, $this->userProvider);
    }

    public function testSupports()
    {
        $request = new Request();

        $this->guardAuthenticator->expects($this->once())
            ->method('supports')
            ->with($request)
            ->willReturn(true);

        $this->assertTrue($this->authenticator->supports($request));
    }

    public function testNoSupport()
    {
        $request = new Request();

        $this->guardAuthenticator->expects($this->once())
            ->method('supports')
            ->with($request)
            ->willReturn(false);

        $this->assertFalse($this->authenticator->supports($request));
    }

    public function testAuthenticate()
    {
        $request = new Request();

        $credentials = ['password' => 's3cr3t'];
        $this->guardAuthenticator->expects($this->once())
            ->method('getCredentials')
            ->with($request)
            ->willReturn($credentials);

        $user = new User('test', null, ['ROLE_USER']);
        $this->guardAuthenticator->expects($this->once())
            ->method('getUser')
            ->with($credentials, $this->userProvider)
            ->willReturn($user);

        $passport = $this->authenticator->authenticate($request);
        $this->assertEquals($user, $passport->getUser());
        $this->assertTrue($passport->hasBadge(CustomCredentials::class));

        $this->guardAuthenticator->expects($this->once())
            ->method('checkCredentials')
            ->with($credentials, $user)
            ->willReturn(true);

        $passport->getBadge(CustomCredentials::class)->executeCustomChecker($user);
    }

    public function testAuthenticateNoUser()
    {
        $this->expectException(UsernameNotFoundException::class);

        $request = new Request();

        $credentials = ['password' => 's3cr3t'];
        $this->guardAuthenticator->expects($this->once())
            ->method('getCredentials')
            ->with($request)
            ->willReturn($credentials);

        $this->guardAuthenticator->expects($this->once())
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
        $this->guardAuthenticator->expects($this->once())
            ->method('getCredentials')
            ->with($request)
            ->willReturn($credentials);

        $this->guardAuthenticator->expects($this->once())
            ->method('supportsRememberMe')
            ->willReturn($rememberMeSupported);

        $passport = $this->authenticator->authenticate($request);
        $this->assertEquals($rememberMeSupported, $passport->hasBadge(RememberMeBadge::class));
    }

    public function provideRememberMeData()
    {
        yield [true];
        yield [false];
    }

    public function testCreateAuthenticatedToken()
    {
        $user = new User('test', null, ['ROLE_USER']);

        $token = new PostAuthenticationGuardToken($user, 'main', ['ROLE_USER']);
        $this->guardAuthenticator->expects($this->once())
            ->method('createAuthenticatedToken')
            ->with($user, 'main')
            ->willReturn($token);

        $this->assertSame($token, $this->authenticator->createAuthenticatedToken(new SelfValidatingPassport(new UserBadge('test', function () use ($user) { return $user; })), 'main'));
    }

    public function testHandleSuccess()
    {
        $request = new Request();
        $token = new PostAuthenticationGuardToken(new User('test', null, ['ROLE_USER']), 'main', ['ROLE_USER']);

        $response = new Response();
        $this->guardAuthenticator->expects($this->once())
            ->method('onAuthenticationSuccess')
            ->with($request, $token)
            ->willReturn($response);

        $this->assertSame($response, $this->authenticator->onAuthenticationSuccess($request, $token, 'main'));
    }

    public function testOnFailure()
    {
        $request = new Request();
        $exception = new AuthenticationException();

        $response = new Response();
        $this->guardAuthenticator->expects($this->once())
            ->method('onAuthenticationFailure')
            ->with($request, $exception)
            ->willReturn($response);

        $this->assertSame($response, $this->authenticator->onAuthenticationFailure($request, $exception));
    }
}
