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

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\InMemoryUserProvider;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\FormLoginAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\PasswordUpgradeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Http\Tests\Authenticator\Fixtures\PasswordUpgraderProvider;

class FormLoginAuthenticatorTest extends TestCase
{
    private InMemoryUserProvider $userProvider;
    private MockObject&AuthenticationSuccessHandlerInterface $successHandler;
    private MockObject&AuthenticationFailureHandlerInterface $failureHandler;
    private FormLoginAuthenticator $authenticator;

    protected function setUp(): void
    {
        $this->userProvider = new InMemoryUserProvider(['test' => ['password' => 's$cr$t']]);
        $this->successHandler = $this->createMock(AuthenticationSuccessHandlerInterface::class);
        $this->failureHandler = $this->createMock(AuthenticationFailureHandlerInterface::class);
    }

    public function testHandleWhenUsernameEmpty()
    {
        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('The key "_username" must be a non-empty string.');

        $request = Request::create('/login_check', 'POST', ['_username' => '', '_password' => 's$cr$t']);
        $request->setSession($this->createSession());

        $this->setUpAuthenticator();
        $this->authenticator->authenticate($request);
    }

    public function testHandleWhenPasswordEmpty()
    {
        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('The key "_password" must be a non-empty string.');

        $request = Request::create('/login_check', 'POST', ['_username' => 'foo', '_password' => '']);
        $request->setSession($this->createSession());

        $this->setUpAuthenticator();
        $this->authenticator->authenticate($request);
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
            $this->expectExceptionMessage('Username too long.');
        }

        $request = Request::create('/login_check', 'POST', ['_username' => $username, '_password' => 's$cr$t']);
        $request->setSession($this->createSession());

        $this->setUpAuthenticator();
        $this->authenticator->authenticate($request);
    }

    public static function provideUsernamesForLength()
    {
        yield [str_repeat('x', UserBadge::MAX_USERNAME_LENGTH + 1), false];
        yield [str_repeat('x', UserBadge::MAX_USERNAME_LENGTH - 1), true];
    }

    /**
     * @dataProvider postOnlyDataProvider
     */
    public function testHandleNonStringUsernameWithArray($postOnly)
    {
        $request = Request::create('/login_check', 'POST', ['_username' => []]);
        $request->setSession($this->createSession());

        $this->setUpAuthenticator(['post_only' => $postOnly]);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('The key "_username" must be a string, "array" given.');

        $this->authenticator->authenticate($request);
    }

    /**
     * @dataProvider postOnlyDataProvider
     */
    public function testHandleNonStringUsernameWithInt($postOnly)
    {
        $request = Request::create('/login_check', 'POST', ['_username' => 42]);
        $request->setSession($this->createSession());

        $this->setUpAuthenticator(['post_only' => $postOnly]);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('The key "_username" must be a string, "integer" given.');

        $this->authenticator->authenticate($request);
    }

    /**
     * @dataProvider postOnlyDataProvider
     */
    public function testHandleNonStringUsernameWithObject($postOnly)
    {
        $request = Request::create('/login_check', 'POST', ['_username' => new \stdClass()]);
        $request->setSession($this->createSession());

        $this->setUpAuthenticator(['post_only' => $postOnly]);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('The key "_username" must be a string, "object" given.');

        $this->authenticator->authenticate($request);
    }

    /**
     * @dataProvider postOnlyDataProvider
     */
    public function testHandleNonStringUsernameWithToString($postOnly)
    {
        $usernameObject = $this->createMock(DummyUserClass::class);
        $usernameObject->expects($this->once())->method('__toString')->willReturn('someUsername');

        $request = Request::create('/login_check', 'POST', ['_username' => $usernameObject, '_password' => 's$cr$t']);
        $request->setSession($this->createSession());

        $this->setUpAuthenticator(['post_only' => $postOnly]);
        $this->authenticator->authenticate($request);
    }

    /**
     * @dataProvider postOnlyDataProvider
     */
    public function testHandleNonStringPasswordWithArray(bool $postOnly)
    {
        $request = Request::create('/login_check', 'POST', ['_username' => 'foo', '_password' => []]);
        $request->setSession($this->createSession());

        $this->setUpAuthenticator(['post_only' => $postOnly]);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('The key "_password" must be a string, "array" given.');

        $this->authenticator->authenticate($request);
    }

    /**
     * @dataProvider postOnlyDataProvider
     */
    public function testHandleNonStringPasswordWithToString(bool $postOnly)
    {
        $passwordObject = new class() {
            public function __toString(): string
            {
                return 's$cr$t';
            }
        };

        $request = Request::create('/login_check', 'POST', ['_username' => 'foo', '_password' => $passwordObject]);
        $request->setSession($this->createSession());

        $this->setUpAuthenticator(['post_only' => $postOnly]);
        $passport = $this->authenticator->authenticate($request);

        /** @var PasswordCredentials $credentialsBadge */
        $credentialsBadge = $passport->getBadge(PasswordCredentials::class);
        $this->assertSame('s$cr$t', $credentialsBadge->getPassword());
    }

    /**
     * @dataProvider postOnlyDataProvider
     */
    public function testHandleNonStringCsrfTokenWithArray($postOnly)
    {
        $request = Request::create('/login_check', 'POST', ['_username' => 'foo', '_password' => 'bar', '_csrf_token' => []]);
        $request->setSession($this->createSession());

        $this->setUpAuthenticator(['post_only' => $postOnly]);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('The key "_csrf_token" must be a string, "array" given.');

        $this->authenticator->authenticate($request);
    }

    /**
     * @dataProvider postOnlyDataProvider
     */
    public function testHandleNonStringCsrfTokenWithInt($postOnly)
    {
        $request = Request::create('/login_check', 'POST', ['_username' => 'foo', '_password' => 'bar', '_csrf_token' => 42]);
        $request->setSession($this->createSession());

        $this->setUpAuthenticator(['post_only' => $postOnly]);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('The key "_csrf_token" must be a string, "integer" given.');

        $this->authenticator->authenticate($request);
    }

    /**
     * @dataProvider postOnlyDataProvider
     */
    public function testHandleNonStringCsrfTokenWithObject($postOnly)
    {
        $request = Request::create('/login_check', 'POST', ['_username' => 'foo', '_password' => 'bar', '_csrf_token' => new \stdClass()]);
        $request->setSession($this->createSession());

        $this->setUpAuthenticator(['post_only' => $postOnly]);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('The key "_csrf_token" must be a string, "object" given.');

        $this->authenticator->authenticate($request);
    }

    public static function postOnlyDataProvider()
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

        $this->userProvider = new PasswordUpgraderProvider(['test' => ['password' => 's$cr$t']]);

        $this->setUpAuthenticator();
        $passport = $this->authenticator->authenticate($request);
        $this->assertTrue($passport->hasBadge(PasswordUpgradeBadge::class));
        $badge = $passport->getBadge(PasswordUpgradeBadge::class);
        $this->assertEquals('s$cr$t', $badge->getAndErasePlaintextPassword());
    }

    /**
     * @dataProvider provideContentTypes()
     */
    public function testSupportsFormOnly(string $contentType, bool $shouldSupport)
    {
        $request = new Request();
        $request->headers->set('CONTENT_TYPE', $contentType);
        $request->server->set('REQUEST_URI', '/login_check');
        $request->setMethod('POST');

        $this->setUpAuthenticator(['form_only' => true]);

        $this->assertSame($shouldSupport, $this->authenticator->supports($request));
    }

    public static function provideContentTypes()
    {
        yield ['application/json', false];
        yield ['application/x-www-form-urlencoded', true];
    }

    private function setUpAuthenticator(array $options = [])
    {
        $this->authenticator = new FormLoginAuthenticator(new HttpUtils(), $this->userProvider, $this->successHandler, $this->failureHandler, $options);
    }

    private function createSession()
    {
        return $this->createMock(SessionInterface::class);
    }
}

class DummyUserClass
{
    public function __toString(): string
    {
        return '';
    }
}
