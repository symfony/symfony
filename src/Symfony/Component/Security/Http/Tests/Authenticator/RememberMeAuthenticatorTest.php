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
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\RememberMeAuthenticator;
use Symfony\Component\Security\Http\RememberMe\RememberMeDetails;
use Symfony\Component\Security\Http\RememberMe\RememberMeHandlerInterface;
use Symfony\Component\Security\Http\RememberMe\ResponseListener;

class RememberMeAuthenticatorTest extends TestCase
{
    private RememberMeHandlerInterface $rememberMeHandler;
    private TokenStorage $tokenStorage;
    private RememberMeAuthenticator $authenticator;

    protected function setUp(): void
    {
        $this->rememberMeHandler = $this->createStub(RememberMeHandlerInterface::class);
        $this->tokenStorage = new TokenStorage();
        $this->authenticator = new RememberMeAuthenticator($this->rememberMeHandler, $this->tokenStorage, '_remember_me_cookie');
    }

    public function testSupportsTokenStorageWithToken()
    {
        $this->tokenStorage->setToken(new UsernamePasswordToken(new InMemoryUser('username', 'credentials'), 'main'));

        $this->assertFalse($this->authenticator->supports(Request::create('/')));
    }

    #[DataProvider('provideSupportsData')]
    public function testSupports($request, $support)
    {
        $this->assertSame($support, $this->authenticator->supports($request));
    }

    public static function provideSupportsData()
    {
        yield [Request::create('/'), false];

        $request = Request::create('/', 'GET', [], ['_remember_me_cookie' => 'rememberme']);
        yield [$request, null];

        $request = Request::create('/', 'GET', [], ['_remember_me_cookie' => 'rememberme']);
        $request->attributes->set(ResponseListener::COOKIE_ATTR_NAME, new Cookie('_remember_me_cookie', null));
        yield [$request, false];

        $request = Request::create('/', 'GET', [], ['_remember_me_cookie' => '0']);
        yield [$request, false];
    }

    #[DataProvider('provideUnsupportedReasons')]
    public function testUnsupportedReasonNamesTheFailingCondition(Request $request, ?string $expected)
    {
        $this->assertSame($expected, $this->authenticator->getUnsupportedReason($request));
    }

    public static function provideUnsupportedReasons(): iterable
    {
        yield 'no cookie' => [Request::create('/'), 'the request has no "_remember_me_cookie" cookie'];

        $request = Request::create('/', 'GET', [], ['_remember_me_cookie' => 'rememberme']);
        $request->attributes->set(ResponseListener::COOKIE_ATTR_NAME, new Cookie('_remember_me_cookie', null));
        yield 'cookie being cleared' => [$request, 'the "_remember_me_cookie" cookie is being cleared by this request'];

        yield 'empty cookie' => [
            Request::create('/', 'GET', [], ['_remember_me_cookie' => '0']),
            'the "_remember_me_cookie" cookie is empty or not a scalar',
        ];

        yield 'supported' => [Request::create('/', 'GET', [], ['_remember_me_cookie' => 'rememberme']), null];
    }

    public function testUnsupportedReasonWhenAlreadyAuthenticated()
    {
        $this->tokenStorage->setToken(new UsernamePasswordToken(new InMemoryUser('username', 'credentials'), 'main'));

        $this->assertSame(
            'a token is already stored, so this request is already authenticated',
            $this->authenticator->getUnsupportedReason(Request::create('/', 'GET', [], ['_remember_me_cookie' => 'rememberme'])),
        );
    }

    /**
     * supports() and getUnsupportedReason() evaluate the same conditions separately, so they must
     * be kept in step: a declined request always has a reason, and a supported one never does.
     * supports() returns null, not true, when it supports the request, since this authenticator
     * supports lazy firewalls.
     */
    #[DataProvider('provideSupportsData')]
    public function testUnsupportedReasonAgreesWithSupports(Request $request, ?bool $support)
    {
        $this->assertSame(false === $support, null !== $this->authenticator->getUnsupportedReason($request));
    }

    public function testAuthenticate()
    {
        $rememberMeHandler = $this->createMock(RememberMeHandlerInterface::class);
        $rememberMeDetails = new RememberMeDetails('wouter', 1, 'secret');
        $request = Request::create('/', 'GET', [], ['_remember_me_cookie' => $rememberMeDetails->toString()]);
        $passport = (new RememberMeAuthenticator($rememberMeHandler, $this->tokenStorage, '_remember_me_cookie'))->authenticate($request);

        $rememberMeHandler->expects($this->once())->method('consumeRememberMeCookie')->with($this->callback(static fn ($arg) => $rememberMeDetails == $arg));
        $passport->getUser(); // trigger the user loader
    }

    public function testAuthenticateLegacyCookieFormat()
    {
        $rememberMeHandler = $this->createMock(RememberMeHandlerInterface::class);
        $rememberMeDetails = new RememberMeDetails('wouter', 1, 'secret');
        $request = Request::create('/', 'GET', [], ['_remember_me_cookie' => strtr(InMemoryUser::class, '\\', '.').$rememberMeDetails->toString()]);
        $passport = (new RememberMeAuthenticator($rememberMeHandler, $this->tokenStorage, '_remember_me_cookie'))->authenticate($request);

        $rememberMeHandler->expects($this->once())->method('consumeRememberMeCookie')->with($this->callback(static fn ($arg) => $rememberMeDetails == $arg));
        $passport->getUser(); // trigger the user loader
    }

    public function testAuthenticateWithoutToken()
    {
        $this->expectException(\LogicException::class);

        $this->authenticator->authenticate(Request::create('/'));
    }

    public function testAuthenticateWithoutOldToken()
    {
        $request = Request::create('/', 'GET', [], ['_remember_me_cookie' => base64_encode('foo:bar')]);

        $this->expectException(AuthenticationException::class);

        $this->authenticator->authenticate($request);
    }

    public function testAuthenticateWithTokenWithoutDelimiter()
    {
        $request = Request::create('/', 'GET', [], ['_remember_me_cookie' => 'invalid']);

        $this->expectException(AuthenticationException::class);

        $this->authenticator->authenticate($request);
    }
}
