<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\RememberMe;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\RememberMe\PersistentToken;
use Symfony\Component\Security\Core\Authentication\RememberMe\TokenProviderInterface;
use Symfony\Component\Security\Core\Authentication\RememberMe\TokenVerifierInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CookieTheftException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\InMemoryUserProvider;
use Symfony\Component\Security\Http\RememberMe\PersistentRememberMeHandler;
use Symfony\Component\Security\Http\RememberMe\RememberMeDetails;
use Symfony\Component\Security\Http\RememberMe\ResponseListener;

class PersistentRememberMeHandlerTest extends TestCase
{
    private MockObject&TokenProviderInterface $tokenProvider;
    private InMemoryUserProvider $userProvider;
    private RequestStack $requestStack;
    private Request $request;
    private PersistentRememberMeHandler $handler;

    protected function setUp(): void
    {
        $this->tokenProvider = $this->createMock(TokenProviderInterface::class);
        $this->userProvider = new InMemoryUserProvider();
        $this->userProvider->createUser(new InMemoryUser('wouter', null));
        $this->requestStack = new RequestStack();
        $this->request = Request::create('/login');
        $this->requestStack->push($this->request);
        $this->handler = new PersistentRememberMeHandler($this->tokenProvider, $this->userProvider, $this->requestStack, []);
    }

    public function testCreateRememberMeCookie()
    {
        $this->tokenProvider->expects($this->once())
            ->method('createNewToken')
            ->with($this->callback(fn ($token) => $token instanceof PersistentToken && 'wouter' === $token->getUserIdentifier()));

        $this->handler->createRememberMeCookie(new InMemoryUser('wouter', null));
    }

    public function testClearRememberMeCookie()
    {
        $this->tokenProvider->expects($this->once())
            ->method('deleteTokenBySeries')
            ->with('series1');

        $this->request->cookies->set('REMEMBERME', (new RememberMeDetails(InMemoryUser::class, 'wouter', 0, 'series1:tokenvalue', false))->toString());

        $this->handler->clearRememberMeCookie();

        $this->assertTrue($this->request->attributes->has(ResponseListener::COOKIE_ATTR_NAME));

        /** @var Cookie $cookie */
        $cookie = $this->request->attributes->get(ResponseListener::COOKIE_ATTR_NAME);
        $this->assertNull($cookie->getValue());
    }

    public function testClearRememberMeCookieMalformedCookie()
    {
        $this->tokenProvider->expects($this->exactly(0))
            ->method('deleteTokenBySeries');

        $this->request->cookies->set('REMEMBERME', 'malformed');

        $this->handler->clearRememberMeCookie();

        $this->assertTrue($this->request->attributes->has(ResponseListener::COOKIE_ATTR_NAME));

        /** @var Cookie $cookie */
        $cookie = $this->request->attributes->get(ResponseListener::COOKIE_ATTR_NAME);
        $this->assertNull($cookie->getValue());
    }

    public function testConsumeRememberMeCookieValid()
    {
        if (method_exists(PersistentToken::class, 'getClass')) {
            $persistentToken = new PersistentToken(InMemoryUser::class, 'wouter', 'series1', 'tokenvalue', $lastUsed = new \DateTimeImmutable('-10 min'), false);
        } else {
            $persistentToken = new PersistentToken('wouter', 'series1', 'tokenvalue', $lastUsed = new \DateTimeImmutable('-10 min'));
        }

        $this->tokenProvider->expects($this->any())
            ->method('loadTokenBySeries')
            ->with('series1')
            ->willReturn($persistentToken)
        ;

        $this->tokenProvider->expects($this->once())->method('updateToken')->with('series1');

        $rememberMeDetails = new RememberMeDetails(InMemoryUser::class, 'wouter', 360, 'series1:tokenvalue', false);
        $this->handler->consumeRememberMeCookie($rememberMeDetails);

        // assert that the cookie has been updated with a new base64 encoded token value
        $this->assertTrue($this->request->attributes->has(ResponseListener::COOKIE_ATTR_NAME));

        /** @var Cookie $cookie */
        $cookie = $this->request->attributes->get(ResponseListener::COOKIE_ATTR_NAME);
        $rememberParts = explode(':', $rememberMeDetails->toString(), 4);
        $cookieParts = explode(':', $cookie->getValue(), 4);

        $this->assertSame(method_exists(PersistentToken::class, 'getClass') ? $rememberParts[0] : '', $cookieParts[0]); // class
        $this->assertSame($rememberParts[1], $cookieParts[1]); // identifier
        $this->assertEqualsWithDelta($lastUsed->getTimestamp() + 31536000, (int) $cookieParts[2], 2); // expire
        $this->assertNotSame($rememberParts[3], $cookieParts[3]); // value
        $this->assertSame(explode(':', $rememberParts[3])[0], explode(':', $cookieParts[3])[0]); // series
    }

    public function testConsumeRememberMeCookieInvalidOwner()
    {
        if (method_exists(PersistentToken::class, 'getClass')) {
            $persistentToken = new PersistentToken(InMemoryUser::class, 'wouter', 'series1', 'tokenvalue', new \DateTimeImmutable('-10 min'), false);
        } else {
            $persistentToken = new PersistentToken('wouter', 'series1', 'tokenvalue', new \DateTimeImmutable('-10 min'));
        }

        $this->tokenProvider->expects($this->any())
            ->method('loadTokenBySeries')
            ->with('series1')
            ->willReturn($persistentToken)
        ;

        $rememberMeDetails = new RememberMeDetails(InMemoryUser::class, 'jeremy', 360, 'series1:tokenvalue', false);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('The cookie\'s hash is invalid.');
        $this->handler->consumeRememberMeCookie($rememberMeDetails);
    }

    public function testConsumeRememberMeCookieInvalidValue()
    {
        if (method_exists(PersistentToken::class, 'getClass')) {
            $persistentToken = new PersistentToken(InMemoryUser::class, 'wouter', 'series1', 'tokenvalue', new \DateTimeImmutable('-10 min'), false);
        } else {
            $persistentToken = new PersistentToken('wouter', 'series1', 'tokenvalue', new \DateTimeImmutable('-10 min'));
        }

        $this->tokenProvider->expects($this->any())
            ->method('loadTokenBySeries')
            ->with('series1')
            ->willReturn($persistentToken)
        ;

        $rememberMeDetails = new RememberMeDetails(InMemoryUser::class, 'wouter', 360, 'series1:tokenvalue:somethingelse', false);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('This token was already used. The account is possibly compromised.');
        $this->handler->consumeRememberMeCookie($rememberMeDetails);
    }

    public function testConsumeRememberMeCookieValidByValidatorWithoutUpdate()
    {
        $verifier = $this->createMock(TokenVerifierInterface::class);
        $handler = new PersistentRememberMeHandler($this->tokenProvider, $this->userProvider, $this->requestStack, [], null, $verifier);

        if (method_exists(PersistentToken::class, 'getClass')) {
            $persistentToken = new PersistentToken(InMemoryUser::class, 'wouter', 'series1', 'tokenvalue', new \DateTimeImmutable('30 seconds'), false);
        } else {
            $persistentToken = new PersistentToken('wouter', 'series1', 'tokenvalue', new \DateTimeImmutable('30 seconds'));
        }

        $this->tokenProvider->expects($this->any())
            ->method('loadTokenBySeries')
            ->with('series1')
            ->willReturn($persistentToken)
        ;

        $verifier->expects($this->any())
            ->method('verifyToken')
            ->with($persistentToken, 'oldTokenValue')
            ->willReturn(true)
        ;

        $rememberMeDetails = new RememberMeDetails(InMemoryUser::class, 'wouter', 360, 'series1:oldTokenValue', false);
        $handler->consumeRememberMeCookie($rememberMeDetails);

        $this->assertFalse($this->request->attributes->has(ResponseListener::COOKIE_ATTR_NAME));
    }

    public function testConsumeRememberMeCookieInvalidToken()
    {
        if (method_exists(PersistentToken::class, 'getClass')) {
            $persistentToken = new PersistentToken(InMemoryUser::class, 'wouter', 'series1', 'tokenvalue1', new \DateTimeImmutable('-10 min'), false);
        } else {
            $persistentToken = new PersistentToken('wouter', 'series1', 'tokenvalue1', new \DateTimeImmutable('-10 min'));
        }

        $this->tokenProvider->expects($this->any())
            ->method('loadTokenBySeries')
            ->with('series1')
            ->willReturn($persistentToken);

        $this->tokenProvider->expects($this->never())->method('updateToken')->with('series1');

        $this->expectException(CookieTheftException::class);

        $this->handler->consumeRememberMeCookie(new RememberMeDetails(InMemoryUser::class, 'wouter', 360, 'series1:tokenvalue', false));
    }

    public function testConsumeRememberMeCookieExpired()
    {
        if (method_exists(PersistentToken::class, 'getClass')) {
            $persistentToken = new PersistentToken(InMemoryUser::class, 'wouter', 'series1', 'tokenvalue', new \DateTimeImmutable('@'.(time() - (31536000 + 1))), false);
        } else {
            $persistentToken = new PersistentToken('wouter', 'series1', 'tokenvalue', new \DateTimeImmutable('@'.(time() - (31536000 + 1))));
        }

        $this->tokenProvider->expects($this->any())
            ->method('loadTokenBySeries')
            ->with('series1')
            ->willReturn($persistentToken);

        $this->tokenProvider->expects($this->never())->method('updateToken')->with('series1');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('The cookie has expired.');

        $this->handler->consumeRememberMeCookie(new RememberMeDetails(InMemoryUser::class, 'wouter', 360, 'series1:tokenvalue', false));
    }

    public function testBase64EncodedTokens()
    {
        if (method_exists(PersistentToken::class, 'getClass')) {
            $persistentToken = new PersistentToken(InMemoryUser::class, 'wouter', 'series1', 'tokenvalue', new \DateTimeImmutable('-10 min'), false);
        } else {
            $persistentToken = new PersistentToken('wouter', 'series1', 'tokenvalue', new \DateTimeImmutable('-10 min'));
        }

        $this->tokenProvider->expects($this->any())
            ->method('loadTokenBySeries')
            ->with('series1')
            ->willReturn($persistentToken)
        ;

        $this->tokenProvider->expects($this->once())->method('updateToken')->with('series1');

        $rememberMeDetails = new RememberMeDetails(InMemoryUser::class, 'wouter', 360, 'series1:tokenvalue', false);
        $cookieData = explode(RememberMeDetails::COOKIE_DELIMITER, $rememberMeDetails->toString());
        $cookieData[0] = '';
        $rememberMeDetails = RememberMeDetails::fromRawCookie(base64_encode(implode(RememberMeDetails::COOKIE_DELIMITER, $cookieData)));
        $this->handler->consumeRememberMeCookie($rememberMeDetails);
    }

    public function testBase64EncodedTokensLegacyFormat()
    {
        if (method_exists(PersistentToken::class, 'getClass')) {
            $persistentToken = new PersistentToken(InMemoryUser::class, 'wouter', 'series1', 'tokenvalue', new \DateTimeImmutable('-10 min'), false);
        } else {
            $persistentToken = new PersistentToken('wouter', 'series1', 'tokenvalue', new \DateTimeImmutable('-10 min'));
        }

        $this->tokenProvider->expects($this->any())
            ->method('loadTokenBySeries')
            ->with('series1')
            ->willReturn($persistentToken)
        ;

        $this->tokenProvider->expects($this->once())->method('updateToken')->with('series1');

        $rememberMeDetails = new RememberMeDetails(InMemoryUser::class, 'wouter', 360, 'series1:tokenvalue', false);
        $rememberMeDetails = RememberMeDetails::fromRawCookie(base64_encode($rememberMeDetails->toString()));
        $this->handler->consumeRememberMeCookie($rememberMeDetails);
    }
}
