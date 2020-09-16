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
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\User;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\FormLoginAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\PasswordUpgradeBadge;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Http\Tests\Authenticator\Fixtures\PasswordUpgraderProvider;

class FormLoginAuthenticatorTest extends TestCase
{
    private $userProvider;
    private $successHandler;
    private $failureHandler;
    /** @var FormLoginAuthenticator */
    private $authenticator;

    protected function setUp(): void
    {
        $this->userProvider = $this->createMock(UserProviderInterface::class);
        $this->userProvider->expects($this->any())->method('loadUserByUsername')->willReturn(new User('test', 's$cr$t'));
        $this->successHandler = $this->createMock(AuthenticationSuccessHandlerInterface::class);
        $this->failureHandler = $this->createMock(AuthenticationFailureHandlerInterface::class);
    }

    /**
     * @dataProvider provideUsernamesForLength
     */
    public function testHandleWhenUsernameLength($username, $ok)
    {
        if ($ok) {
            $this->expectNotToPerformAssertions();
        } else {
            $this->expectException(BadCredentialsException::class);
            $this->expectExceptionMessage('Invalid username.');
        }

        $request = Request::create('/login_check', 'POST', ['_username' => $username, '_password' => 's$cr$t']);
        $request->setSession($this->createSession());

        $this->setUpAuthenticator();
        $this->authenticator->authenticate($request);
    }

    public function provideUsernamesForLength()
    {
        yield [str_repeat('x', Security::MAX_USERNAME_LENGTH + 1), false];
        yield [str_repeat('x', Security::MAX_USERNAME_LENGTH - 1), true];
    }

    /**
     * @dataProvider postOnlyDataProvider
     */
    public function testHandleNonStringUsernameWithArray($postOnly)
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('The key "_username" must be a string, "array" given.');

        $request = Request::create('/login_check', 'POST', ['_username' => []]);
        $request->setSession($this->createSession());

        $this->setUpAuthenticator(['post_only' => $postOnly]);
        $this->authenticator->authenticate($request);
    }

    /**
     * @dataProvider postOnlyDataProvider
     */
    public function testHandleNonStringUsernameWithInt($postOnly)
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('The key "_username" must be a string, "integer" given.');

        $request = Request::create('/login_check', 'POST', ['_username' => 42]);
        $request->setSession($this->createSession());

        $this->setUpAuthenticator(['post_only' => $postOnly]);
        $this->authenticator->authenticate($request);
    }

    /**
     * @dataProvider postOnlyDataProvider
     */
    public function testHandleNonStringUsernameWithObject($postOnly)
    {
        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('The key "_username" must be a string, "object" given.');

        $request = Request::create('/login_check', 'POST', ['_username' => new \stdClass()]);
        $request->setSession($this->createSession());

        $this->setUpAuthenticator(['post_only' => $postOnly]);
        $this->authenticator->authenticate($request);
    }

    /**
     * @dataProvider postOnlyDataProvider
     */
    public function testHandleNonStringUsernameWithToString($postOnly)
    {
        $usernameObject = $this->getMockBuilder(DummyUserClass::class)->getMock();
        $usernameObject->expects($this->once())->method('__toString')->willReturn('someUsername');

        $request = Request::create('/login_check', 'POST', ['_username' => $usernameObject, '_password' => 's$cr$t']);
        $request->setSession($this->createSession());

        $this->setUpAuthenticator(['post_only' => $postOnly]);
        $this->authenticator->authenticate($request);
    }

    public function postOnlyDataProvider()
    {
        yield [true];
        yield [false];
    }

    public function testCsrfProtection()
    {
        $request = Request::create('/login_check', 'POST', ['_username' => 'wouter', '_password' => 's$cr$t']);
        $request->setSession($this->createSession());

        $this->setUpAuthenticator(['enable_csrf' => true]);
        $passport = $this->authenticator->authenticate($request);
        $this->assertTrue($passport->hasBadge(CsrfTokenBadge::class));
    }

    public function testUpgradePassword()
    {
        $request = Request::create('/login_check', 'POST', ['_username' => 'wouter', '_password' => 's$cr$t']);
        $request->setSession($this->createSession());

        $this->userProvider = $this->createMock(PasswordUpgraderProvider::class);
        $this->userProvider->expects($this->any())->method('loadUserByUsername')->willReturn(new User('test', 's$cr$t'));

        $this->setUpAuthenticator();
        $passport = $this->authenticator->authenticate($request);
        $this->assertTrue($passport->hasBadge(PasswordUpgradeBadge::class));
        $badge = $passport->getBadge(PasswordUpgradeBadge::class);
        $this->assertEquals('s$cr$t', $badge->getAndErasePlaintextPassword());
    }

    private function setUpAuthenticator(array $options = [])
    {
        $this->authenticator = new FormLoginAuthenticator(new HttpUtils(), $this->userProvider, $this->successHandler, $this->failureHandler, $options);
    }

    private function createSession()
    {
        return $this->createMock('Symfony\Component\HttpFoundation\Session\SessionInterface');
    }
}

class DummyUserClass
{
    public function __toString(): string
    {
        return '';
    }
}
