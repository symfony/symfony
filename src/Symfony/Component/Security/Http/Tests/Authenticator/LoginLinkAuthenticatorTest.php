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

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;
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
    private $loginLinkHandler;
    private $successHandler;
    private $failureHandler;
    /** @var LoginLinkAuthenticator */
    private $authenticator;

    protected function setUp(): void
    {
        $this->loginLinkHandler = self::createMock(LoginLinkHandlerInterface::class);
        $this->successHandler = self::createMock(AuthenticationSuccessHandlerInterface::class);
        $this->failureHandler = self::createMock(AuthenticationFailureHandlerInterface::class);
    }

    /**
     * @dataProvider provideSupportData
     */
    public function testSupport(array $options, $request, bool $supported)
    {
        $this->setUpAuthenticator($options);

        self::assertEquals($supported, $this->authenticator->supports($request));
    }

    public function provideSupportData()
    {
        yield [['check_route' => '/validate_link'], Request::create('/validate_link?hash=abc123'), true];
        yield [['check_route' => '/validate_link'], Request::create('/login?hash=abc123'), false];
        yield [['check_route' => '/validate_link', 'check_post_only' => true], Request::create('/validate_link?hash=abc123'), false];
        yield [['check_route' => '/validate_link', 'check_post_only' => true], Request::create('/validate_link?hash=abc123', 'POST'), true];
    }

    public function testSuccessfulAuthenticate()
    {
        $this->setUpAuthenticator();

        $request = Request::create('/login/link/check?stuff=1&user=weaverryan');
        $user = self::createMock(UserInterface::class);
        $this->loginLinkHandler->expects(self::once())
            ->method('consumeLoginLink')
            ->with($request)
            ->willReturn($user);

        $passport = $this->authenticator->authenticate($request);
        self::assertInstanceOf(SelfValidatingPassport::class, $passport);
        /** @var UserBadge $userBadge */
        $userBadge = $passport->getBadge(UserBadge::class);
        self::assertSame($user, $userBadge->getUser());
        self::assertSame('weaverryan', $userBadge->getUserIdentifier());
    }

    public function testUnsuccessfulAuthenticate()
    {
        self::expectException(InvalidLoginLinkAuthenticationException::class);
        $this->setUpAuthenticator();

        $request = Request::create('/login/link/check?stuff=1&user=weaverryan');
        $this->loginLinkHandler->expects(self::once())
            ->method('consumeLoginLink')
            ->with($request)
            ->willThrowException(new ExpiredLoginLinkException());

        $passport = $this->authenticator->authenticate($request);
        // trigger the user loader to try to load the user
        $passport->getBadge(UserBadge::class)->getUser();
    }

    public function testMissingUser()
    {
        self::expectException(InvalidLoginLinkAuthenticationException::class);
        $this->setUpAuthenticator();

        $request = Request::create('/login/link/check?stuff=1');
        self::createMock(UserInterface::class);
        $this->loginLinkHandler->expects(self::never())
            ->method('consumeLoginLink');

        $this->authenticator->authenticate($request);
    }

    public function testPassportBadges()
    {
        $this->setUpAuthenticator();

        $request = Request::create('/login/link/check?stuff=1&user=weaverryan');

        $passport = $this->authenticator->authenticate($request);

        self::assertTrue($passport->hasBadge(RememberMeBadge::class));
    }

    private function setUpAuthenticator(array $options = [])
    {
        $this->authenticator = new LoginLinkAuthenticator($this->loginLinkHandler, new HttpUtils(), $this->successHandler, $this->failureHandler, $options);
    }
}
