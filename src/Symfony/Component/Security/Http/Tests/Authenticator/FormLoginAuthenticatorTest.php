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
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
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
    private AuthenticationSuccessHandlerInterface $successHandler;
    private AuthenticationFailureHandlerInterface $failureHandler;
    private FormLoginAuthenticator $authenticator;

    protected function setUp(): void
    {
        $this->userProvider = new InMemoryUserProvider(['test' => ['password' => 's$cr$t']]);
        $this->successHandler = $this->createStub(AuthenticationSuccessHandlerInterface::class);
        $this->failureHandler = $this->createStub(AuthenticationFailureHandlerInterface::class);
    }

    public function testHandleWhenUsernameEmpty()
    {
        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('The key "_username" must be a non-empty string.');

        $request = Request::create('/login_check', 'POST', ['_username' => '', '_password' => 's$cr$t']);
        $request->setSession(new Session(new MockArraySessionStorage()));

        $this->setUpAuthenticator();
        $this->authenticator->authenticate($request);
    }

    public function testHandleWhenPasswordEmpty()
    {
        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('The key "_password" must be a non-empty string.');

        $request = Request::create('/login_check', 'POST', ['_username' => 'foo', '_password' => '']);
        $request->setSession(new Session(new MockArraySessionStorage()));

        $this->setUpAuthenticator();
        $this->authenticator->authenticate($request);
    }

    #[DataProvider('provideUsernamesForLength')]
    public function testHandleWhenUsernameLength($username, $ok)
    {
        if ($ok) {
            $this->expectNotToPerformAssertions();
        } else {
            $this->expectException(BadCredentialsException::class);
            $this->expectExceptionMessage('Username too long.');
        }

        $request = Request::create('/login_check', 'POST', ['_username' => $username, '_password' => 's$cr$t']);
        $request->setSession(new Session(new MockArraySessionStorage()));

        $this->setUpAuthenticator();
        $this->authenticator->authenticate($request);
    }

    public static function provideUsernamesForLength()
    {
        yield [str_repeat('x', UserBadge::MAX_USERNAME_LENGTH + 1), false];
        yield [str_repeat('x', UserBadge::MAX_USERNAME_LENGTH - 1), true];
    }

    #[DataProvider('postOnlyDataProvider')]
    public function testHandleNonStringUsernameWithArray($postOnly)
    {
        $request = Request::create('/login_check', 'POST', ['_username' => []]);
        $request->setSession(new Session(new MockArraySessionStorage()));

        $this->setUpAuthenticator(['post_only' => $postOnly]);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('The key "_username" must be a string, "array" given.');

        $this->authenticator->authenticate($request);
    }

    #[DataProvider('postOnlyDataProvider')]
    public function testHandleNonStringUsernameWithInt($postOnly)
    {
        $request = Request::create('/login_check', 'POST', ['_username' => 42]);
        $request->setSession(new Session(new MockArraySessionStorage()));

        $this->setUpAuthenticator(['post_only' => $postOnly]);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('The key "_username" must be a string, "integer" given.');

        $this->authenticator->authenticate($request);
    }

    #[DataProvider('postOnlyDataProvider')]
    public function testHandleNonStringUsernameWithObject($postOnly)
    {
        $request = Request::create('/login_check', 'POST', ['_username' => new \stdClass()]);
        $request->setSession(new Session(new MockArraySessionStorage()));

        $this->setUpAuthenticator(['post_only' => $postOnly]);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('The key "_username" must be a string, "object" given.');

        $this->authenticator->authenticate($request);
    }

    #[DataProvider('postOnlyDataProvider')]
    public function testHandleNonStringUsernameWithToString($postOnly)
    {
        $usernameObject = $this->createMock(DummyUserClass::class);
        $usernameObject->expects($this->once())->method('__toString')->willReturn('someUsername');

        $request = Request::create('/login_check', 'POST', ['_username' => $usernameObject, '_password' => 's$cr$t']);
        $request->setSession(new Session(new MockArraySessionStorage()));

        $this->setUpAuthenticator(['post_only' => $postOnly]);
        $this->authenticator->authenticate($request);
    }

    #[DataProvider('postOnlyDataProvider')]
    public function testHandleNonStringPasswordWithArray(bool $postOnly)
    {
        $request = Request::create('/login_check', 'POST', ['_username' => 'foo', '_password' => []]);
        $request->setSession(new Session(new MockArraySessionStorage()));

        $this->setUpAuthenticator(['post_only' => $postOnly]);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('The key "_password" must be a string, "array" given.');

        $this->authenticator->authenticate($request);
    }

    #[DataProvider('postOnlyDataProvider')]
    public function testHandleNonStringPasswordWithToString(bool $postOnly)
    {
        $passwordObject = new class {
            public function __toString(): string
            {
                return 's$cr$t';
            }
        };

        $request = Request::create('/login_check', 'POST', ['_username' => 'foo', '_password' => $passwordObject]);
        $request->setSession(new Session(new MockArraySessionStorage()));

        $this->setUpAuthenticator(['post_only' => $postOnly]);
        $passport = $this->authenticator->authenticate($request);

        /** @var PasswordCredentials $credentialsBadge */
        $credentialsBadge = $passport->getBadge(PasswordCredentials::class);
        $this->assertSame('s$cr$t', $credentialsBadge->getPassword());
    }

    #[DataProvider('postOnlyDataProvider')]
    public function testHandleNonStringCsrfTokenWithArray($postOnly)
    {
        $request = Request::create('/login_check', 'POST', ['_username' => 'foo', '_password' => 'bar', '_csrf_token' => []]);
        $request->setSession(new Session(new MockArraySessionStorage()));

        $this->setUpAuthenticator(['post_only' => $postOnly]);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('The key "_csrf_token" must be a string, "array" given.');

        $this->authenticator->authenticate($request);
    }

    #[DataProvider('postOnlyDataProvider')]
    public function testHandleNonStringCsrfTokenWithInt($postOnly)
    {
        $request = Request::create('/login_check', 'POST', ['_username' => 'foo', '_password' => 'bar', '_csrf_token' => 42]);
        $request->setSession(new Session(new MockArraySessionStorage()));

        $this->setUpAuthenticator(['post_only' => $postOnly]);

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('The key "_csrf_token" must be a string, "integer" given.');

        $this->authenticator->authenticate($request);
    }

    #[DataProvider('postOnlyDataProvider')]
    public function testHandleNonStringCsrfTokenWithObject($postOnly)
    {
        $request = Request::create('/login_check', 'POST', ['_username' => 'foo', '_password' => 'bar', '_csrf_token' => new \stdClass()]);
        $request->setSession(new Session(new MockArraySessionStorage()));

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
        $request->setSession(new Session(new MockArraySessionStorage()));

        $this->setUpAuthenticator(['enable_csrf' => true]);
        $passport = $this->authenticator->authenticate($request);
        $this->assertTrue($passport->hasBadge(CsrfTokenBadge::class));
    }

    public function testUpgradePassword()
    {
        $request = Request::create('/login_check', 'POST', ['_username' => 'wouter', '_password' => 's$cr$t']);
        $request->setSession(new Session(new MockArraySessionStorage()));

        $this->userProvider = new PasswordUpgraderProvider(['test' => ['password' => 's$cr$t']]);

        $this->setUpAuthenticator();
        $passport = $this->authenticator->authenticate($request);
        $this->assertTrue($passport->hasBadge(PasswordUpgradeBadge::class));
        $badge = $passport->getBadge(PasswordUpgradeBadge::class);
        $this->assertEquals('s$cr$t', $badge->getAndErasePlaintextPassword());
    }

    #[DataProvider('provideContentTypes')]
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

    #[DataProvider('provideUnsupportedReasons')]
    public function testUnsupportedReasonNamesTheFailingCondition(array $options, Request $request, string $expected)
    {
        $this->setUpAuthenticator($options);

        $this->assertSame($expected, $this->authenticator->getUnsupportedReason($request));
    }

    public static function provideUnsupportedReasons(): iterable
    {
        yield 'method' => [
            ['check_path' => '/login_check', 'post_only' => true],
            Request::create('/login_check', 'GET'),
            'the request method is "GET", and the "post_only" option requires POST',
        ];

        yield 'path' => [
            ['check_path' => '/login_check', 'post_only' => true],
            Request::create('/elsewhere', 'POST'),
            'the request path "/elsewhere" does not match the "check_path" option "/login_check"',
        ];

        yield 'format' => [
            ['check_path' => '/login_check', 'post_only' => true, 'form_only' => true],
            Request::create('/login_check', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json']),
            'the "Content-Type" header "application/json" is not a form one, and the "form_only" option requires one',
        ];

        // an unmapped content type has no format at all, so the header is what must be named
        yield 'unmapped format' => [
            ['check_path' => '/login_check', 'post_only' => true, 'form_only' => true],
            Request::create('/login_check', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/text']),
            'the "Content-Type" header "application/text" is not a form one, and the "form_only" option requires one',
        ];

        yield 'missing content type' => [
            ['check_path' => '/login_check', 'post_only' => false, 'form_only' => true],
            Request::create('/login_check', 'GET'),
            'the request has no "Content-Type" header, and the "form_only" option requires a form one',
        ];
    }

    /**
     * supports() and getUnsupportedReason() evaluate the same conditions separately, so they must
     * be kept in step over every combination of the options: a declined request always has a
     * reason, an accepted one never does.
     */
    #[DataProvider('provideRequestsForReasonAgreement')]
    public function testUnsupportedReasonAgreesWithSupports(array $options, Request $request)
    {
        $this->setUpAuthenticator($options);

        $this->assertSame(
            false === $this->authenticator->supports($request),
            null !== $this->authenticator->getUnsupportedReason($request),
        );
    }

    public static function provideRequestsForReasonAgreement(): iterable
    {
        foreach ([true, false] as $postOnly) {
            foreach ([true, false] as $formOnly) {
                foreach (['POST', 'GET'] as $method) {
                    foreach (['/login_check', '/elsewhere'] as $path) {
                        foreach ([null, 'application/x-www-form-urlencoded', 'application/json', 'application/text'] as $contentType) {
                            $options = ['check_path' => '/login_check', 'post_only' => $postOnly, 'form_only' => $formOnly];
                            $server = null === $contentType ? [] : ['CONTENT_TYPE' => $contentType];

                            yield \sprintf('post_only: %s, form_only: %s, %s %s, Content-Type: %s', var_export($postOnly, true), var_export($formOnly, true), $method, $path, $contentType ?? 'none') => [
                                $options,
                                Request::create($path, $method, [], [], [], $server),
                            ];
                        }
                    }
                }
            }
        }
    }

    private function setUpAuthenticator(array $options = [])
    {
        $this->authenticator = new FormLoginAuthenticator(new HttpUtils(), $this->userProvider, $this->successHandler, $this->failureHandler, $options);
    }
}

class DummyUserClass
{
    public function __toString(): string
    {
        return '';
    }
}
