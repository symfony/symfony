<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Authenticator;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\LoginLinkAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Http\LoginLink\Exception\ExpiredLoginLinkException;
use Symfony\Component\Security\Http\LoginLink\Exception\InvalidLoginLinkAuthenticationException;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;

class LoginLinkAuthenticatorTest extends TestCase
{
    private LoginLinkHandlerInterface $loginLinkHandler;
    private AuthenticationSuccessHandlerInterface $successHandler;
    private AuthenticationFailureHandlerInterface $failureHandler;
    private LoginLinkAuthenticator $authenticator;

    protected function setUp(): void
    {
        $this->loginLinkHandler = $this->createStub(LoginLinkHandlerInterface::class);
        $this->successHandler = $this->createStub(AuthenticationSuccessHandlerInterface::class);
        $this->failureHandler = $this->createStub(AuthenticationFailureHandlerInterface::class);
    }

    #[DataProvider('provideSupportData')]
    public function testSupport(array $options, $request, bool $supported)
    {
        $this->setUpAuthenticator($options);

        $this->assertEquals($supported, $this->authenticator->supports($request));
    }

    public static function provideSupportData()
    {
        yield [['check_route' => '/validate_link'], Request::create('/validate_link?hash=abc123'), true];
        yield [['check_route' => '/validate_link'], Request::create('/login?hash=abc123'), false];
        yield [['check_route' => '/validate_link', 'check_post_only' => true], Request::create('/validate_link?hash=abc123'), false];
        yield [['check_route' => '/validate_link', 'check_post_only' => true], Request::create('/validate_link?hash=abc123', 'POST'), true];
    }

    public function testSuccessfulAuthenticate()
    {
        $request = Request::create('/login/link/check?stuff=1&user=weaverryan');
        $user = new InMemoryUser('John', 'password');
        $loginLinkHandler = $this->createMock(LoginLinkHandlerInterface::class);
        $loginLinkHandler->expects($this->once())
            ->method('consumeLoginLink')
            ->with($request)
            ->willReturn($user);

        $passport = (new LoginLinkAuthenticator($loginLinkHandler, new HttpUtils(), $this->successHandler, $this->failureHandler, []))->authenticate($request);
        $this->assertInstanceOf(SelfValidatingPassport::class, $passport);
        /** @var UserBadge $userBadge */
        $userBadge = $passport->getBadge(UserBadge::class);
        $this->assertSame($user, $userBadge->getUser());
        $this->assertSame('weaverryan', $userBadge->getUserIdentifier());
    }

    public function testUnsuccessfulAuthenticate()
    {
        $this->setUpAuthenticator();

        $request = Request::create('/login/link/check?stuff=1&user=weaverryan');
        $loginLinkHandler = $this->createMock(LoginLinkHandlerInterface::class);
        $loginLinkHandler->expects($this->once())
            ->method('consumeLoginLink')
            ->with($request)
            ->willThrowException(new ExpiredLoginLinkException());

        $passport = (new LoginLinkAuthenticator($loginLinkHandler, new HttpUtils(), $this->successHandler, $this->failureHandler, []))->authenticate($request);

        $this->expectException(InvalidLoginLinkAuthenticationException::class);

        // trigger the user loader to try to load the user
        $passport->getBadge(UserBadge::class)->getUser();
    }

    public function testMissingUser()
    {
        $this->setUpAuthenticator();

        $request = Request::create('/login/link/check?stuff=1');
        $loginLinkHandler = $this->createMock(LoginLinkHandlerInterface::class);
        $loginLinkHandler->expects($this->never())
            ->method('consumeLoginLink');

        $this->expectException(InvalidLoginLinkAuthenticationException::class);

        (new LoginLinkAuthenticator($loginLinkHandler, new HttpUtils(), $this->successHandler, $this->failureHandler, []))->authenticate($request);
    }

    public function testPassportBadges()
    {
        $this->setUpAuthenticator();

        $request = Request::create('/login/link/check?stuff=1&user=weaverryan');

        $passport = $this->authenticator->authenticate($request);

        $this->assertTrue($passport->hasBadge(RememberMeBadge::class));
    }

    private function setUpAuthenticator(array $options = [])
    {
        $this->authenticator = new LoginLinkAuthenticator($this->loginLinkHandler, new HttpUtils(), $this->successHandler, $this->failureHandler, $options);
    }
}
