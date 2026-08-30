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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Security\Core\Authentication\RememberMe\CacheTokenVerifier;
use Symfony\Component\Security\Core\Authentication\RememberMe\InMemoryTokenProvider;
use Symfony\Component\Security\Core\Authentication\RememberMe\PersistentToken;
use Symfony\Component\Security\Core\Authentication\RememberMe\TokenProviderInterface;
use Symfony\Component\Security\Core\Authentication\RememberMe\TokenVerifierInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CookieTheftException;
use Symfony\Component\Security\Core\Exception\TokenNotFoundException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\InMemoryUserProvider;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\RememberMe\PersistentRememberMeHandler;
use Symfony\Component\Security\Http\RememberMe\RememberMeDetails;
use Symfony\Component\Security\Http\RememberMe\ResponseListener;
use Symfony\Component\Security\Http\Tests\Fixtures\PasswordlessUser;

class PersistentRememberMeHandlerTest extends TestCase
{
    private TokenProviderInterface $tokenProvider;
    private InMemoryUserProvider $userProvider;
    private RequestStack $requestStack;
    private Request $request;
    private string $password;
    private string $email;

    protected function setUp(): void
    {
        $this->tokenProvider = new InMemoryTokenProvider();
        $this->userProvider = new InMemoryUserProvider();
        $this->userProvider->createUser(new InMemoryUser('wouter', null));
        $this->requestStack = new RequestStack();
        $this->request = Request::create('/login');
        $this->requestStack->push($this->request);
        $this->password = 'p4ssw0rd';
        $this->email = 'wouter@example.com';
    }

    public function testCreateRememberMeCookie()
    {
        $tokenProvider = $this->createMock(TokenProviderInterface::class);
        $tokenProvider->expects($this->once())
            ->method('createNewToken')
            ->with($this->callback(static fn ($token) => $token instanceof PersistentToken && 'wouter' === $token->getUserIdentifier()));

        (new PersistentRememberMeHandler($tokenProvider, $this->userProvider, $this->requestStack, []))->createRememberMeCookie(new InMemoryUser('wouter', null));
    }

    public function testClearRememberMeCookie()
    {
        $tokenProvider = $this->createMock(TokenProviderInterface::class);
        $tokenProvider->expects($this->once())
            ->method('deleteTokenBySeries')
            ->with('series1');

        $this->request->cookies->set('REMEMBERME', (new RememberMeDetails('wouter', 0, 'series1:tokenvalue'))->toString());

        (new PersistentRememberMeHandler($tokenProvider, $this->userProvider, $this->requestStack, []))->clearRememberMeCookie();

        $this->assertTrue($this->request->attributes->has(ResponseListener::COOKIE_ATTR_NAME));

        /** @var Cookie $cookie */
        $cookie = $this->request->attributes->get(ResponseListener::COOKIE_ATTR_NAME);
        $this->assertNull($cookie->getValue());
    }

    public function testClearRememberMeCookieMalformedCookie()
    {
        $tokenProvider = $this->createMock(TokenProviderInterface::class);
        $tokenProvider->expects($this->exactly(0))
            ->method('deleteTokenBySeries');

        $this->request->cookies->set('REMEMBERME', 'malformed');

        (new PersistentRememberMeHandler($tokenProvider, $this->userProvider, $this->requestStack, []))->clearRememberMeCookie();

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

        $tokenProvider = $this->createMock(TokenProviderInterface::class);
        $tokenProvider
            ->method('loadTokenBySeries')
            ->willReturnMap([
                ['series1', $persistentToken],
            ])
        ;

        $tokenProvider->expects($this->once())->method('updateToken')->with('series1');

        $rememberMeDetails = new RememberMeDetails('wouter', 360, 'series1:tokenvalue');
        (new PersistentRememberMeHandler($tokenProvider, $this->userProvider, $this->requestStack, []))->consumeRememberMeCookie($rememberMeDetails);

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

        $tokenProvider = $this->createMock(TokenProviderInterface::class);
        $tokenProvider
            ->expects($this->once())
            ->method('loadTokenBySeries')
            ->with('series1')
            ->willReturn($persistentToken)
        ;

        $rememberMeDetails = new RememberMeDetails('jeremy', 360, 'series1:tokenvalue');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('The cookie\'s hash is invalid.');
        (new PersistentRememberMeHandler($tokenProvider, $this->userProvider, $this->requestStack, []))->consumeRememberMeCookie($rememberMeDetails);
    }

    public function testConsumeRememberMeCookieInvalidValue()
    {
        if (method_exists(PersistentToken::class, 'getClass')) {
            $persistentToken = new PersistentToken(InMemoryUser::class, 'wouter', 'series1', 'tokenvalue', new \DateTimeImmutable('-10 min'), false);
        } else {
            $persistentToken = new PersistentToken('wouter', 'series1', 'tokenvalue', new \DateTimeImmutable('-10 min'));
        }

        $this->tokenProvider->createNewToken($persistentToken);

        $rememberMeDetails = new RememberMeDetails('wouter', 360, 'series1:tokenvalue:somethingelse');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('This token was already used. The account is possibly compromised.');
        (new PersistentRememberMeHandler($this->tokenProvider, $this->userProvider, $this->requestStack, []))->consumeRememberMeCookie($rememberMeDetails);
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

        $this->tokenProvider->createNewToken($persistentToken);

        $verifier
            ->expects($this->once())
            ->method('verifyToken')
            ->with($persistentToken, 'oldTokenValue')
            ->willReturn(true)
        ;

        $rememberMeDetails = new RememberMeDetails('wouter', 360, 'series1:oldTokenValue');
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

        $tokenProvider = $this->createMock(TokenProviderInterface::class);
        $tokenProvider
            ->method('loadTokenBySeries')
            ->willReturnMap([
                ['series1', $persistentToken],
            ]);

        $tokenProvider->expects($this->never())->method('updateToken')->with('series1');

        $this->expectException(CookieTheftException::class);

        (new PersistentRememberMeHandler($tokenProvider, $this->userProvider, $this->requestStack, []))->consumeRememberMeCookie(new RememberMeDetails('wouter', 360, 'series1:tokenvalue'));
    }

    public function testConsumeRememberMeCookieExpired()
    {
        if (method_exists(PersistentToken::class, 'getClass')) {
            $persistentToken = new PersistentToken(InMemoryUser::class, 'wouter', 'series1', 'tokenvalue', new \DateTimeImmutable('@'.(time() - (31536000 + 1))), false);
        } else {
            $persistentToken = new PersistentToken('wouter', 'series1', 'tokenvalue', new \DateTimeImmutable('@'.(time() - (31536000 + 1))));
        }

        $tokenProvider = $this->createMock(TokenProviderInterface::class);
        $tokenProvider
            ->method('loadTokenBySeries')
            ->willReturnMap([
                ['series1', $persistentToken],
            ]);

        $tokenProvider->expects($this->never())->method('updateToken')->with('series1');

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('The cookie has expired.');

        (new PersistentRememberMeHandler($tokenProvider, $this->userProvider, $this->requestStack, []))->consumeRememberMeCookie(new RememberMeDetails('wouter', 360, 'series1:tokenvalue'));
    }

    public function testBase64EncodedTokens()
    {
        if (method_exists(PersistentToken::class, 'getClass')) {
            $persistentToken = new PersistentToken(InMemoryUser::class, 'wouter', 'series1', 'tokenvalue', new \DateTimeImmutable('-10 min'), false);
        } else {
            $persistentToken = new PersistentToken('wouter', 'series1', 'tokenvalue', new \DateTimeImmutable('-10 min'));
        }

        $tokenProvider = $this->createMock(TokenProviderInterface::class);
        $tokenProvider
            ->method('loadTokenBySeries')
            ->willReturnMap([
                ['series1', $persistentToken],
            ])
        ;

        $tokenProvider->expects($this->once())->method('updateToken')->with('series1');

        $rememberMeDetails = new RememberMeDetails('wouter', 360, 'series1:tokenvalue');
        $cookieData = explode(RememberMeDetails::COOKIE_DELIMITER, $rememberMeDetails->toString());
        $cookieData[0] = '';
        $rememberMeDetails = RememberMeDetails::fromRawCookie(base64_encode(implode(RememberMeDetails::COOKIE_DELIMITER, $cookieData)));
        (new PersistentRememberMeHandler($tokenProvider, $this->userProvider, $this->requestStack, []))->consumeRememberMeCookie($rememberMeDetails);
    }

    public function testBase64EncodedTokensLegacyFormat()
    {
        if (method_exists(PersistentToken::class, 'getClass')) {
            $persistentToken = new PersistentToken(InMemoryUser::class, 'wouter', 'series1', 'tokenvalue', new \DateTimeImmutable('-10 min'), false);
        } else {
            $persistentToken = new PersistentToken('wouter', 'series1', 'tokenvalue', new \DateTimeImmutable('-10 min'));
        }

        $tokenProvider = $this->createMock(TokenProviderInterface::class);
        $tokenProvider
            ->method('loadTokenBySeries')
            ->willReturnMap([
                ['series1', $persistentToken],
            ])
        ;

        $tokenProvider->expects($this->once())->method('updateToken')->with('series1');

        $rememberMeDetails = new RememberMeDetails('wouter', 360, 'series1:tokenvalue');
        $rememberMeDetails = RememberMeDetails::fromRawCookie(base64_encode(strtr(InMemoryUser::class, '\\', '.').$rememberMeDetails->toString()));
        (new PersistentRememberMeHandler($tokenProvider, $this->userProvider, $this->requestStack, []))->consumeRememberMeCookie($rememberMeDetails);
    }

    public function testCreateRememberMeCookieBindsThePasswordByDefault()
    {
        $handler = $this->createHandler(null, $this->createChangingUserProvider());
        $handler->createRememberMeCookie(new InMemoryUser('wouter', $this->password));

        [$series, $tokenValue] = $this->readCookieValue();

        // the cookie carries the stored value, so that a version that does not bind properties accepts it
        $this->assertStringContainsString('.', $tokenValue);
        $this->assertSame($tokenValue, $this->tokenProvider->loadTokenBySeries($series)->getTokenValue());

        $this->password = 'n3wp4ssw0rd';

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('The cookie\'s hash is invalid.');
        $handler->consumeRememberMeCookie(new RememberMeDetails('wouter', 360, $series.':'.$tokenValue));
    }

    public function testConsumeRememberMeCookieSkipsThePasswordTheUserDoesNotExpose()
    {
        $handler = $this->createHandler(null, $this->createPasswordlessUserProvider());
        $handler->createRememberMeCookie(new PasswordlessUser('wouter', $this->email));

        [$series, $tokenValue] = $this->readCookieValue();

        $this->assertStringNotContainsString('.', $tokenValue);
        $this->assertSame($tokenValue, $this->tokenProvider->loadTokenBySeries($series)->getTokenValue());

        $this->email = 'wouter@example.org';

        $user = $handler->consumeRememberMeCookie(new RememberMeDetails('wouter', 360, $series.':'.$tokenValue));

        $this->assertSame('wouter', $user->getUserIdentifier());
    }

    public function testConsumeRememberMeCookieBindsThePasswordByDefaultToAnUnboundToken()
    {
        $handler = $this->createHandler(null, $this->createChangingUserProvider());
        $this->tokenProvider->createNewToken(self::createPersistentToken('wouter', 'series1', 'tokenvalue', new \DateTimeImmutable('-10 min')));

        $handler->consumeRememberMeCookie(new RememberMeDetails('wouter', 360, 'series1:tokenvalue'));

        // the token is bound when it is regenerated
        [$series, $tokenValue] = $this->readCookieValue();
        $this->assertSame('series1', $series);
        $this->assertStringContainsString('.', $tokenValue);
        $this->assertSame($tokenValue, $this->tokenProvider->loadTokenBySeries('series1')->getTokenValue());

        $this->password = 'n3wp4ssw0rd';

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('The cookie\'s hash is invalid.');
        $handler->consumeRememberMeCookie(new RememberMeDetails('wouter', 360, $series.':'.$tokenValue));
    }

    public function testCreateRememberMeCookieWithAnExplicitPropertyTheUserDoesNotExpose()
    {
        $handler = $this->createHandler(['ipAddress'], $this->createChangingUserProvider());

        $this->expectException(NoSuchPropertyException::class);
        $handler->createRememberMeCookie(new InMemoryUser('wouter', $this->password));
    }

    public function testConsumeRememberMeCookieWithAnExplicitPropertyTheUserDoesNotExpose()
    {
        $handler = $this->createHandler(['ipAddress'], $this->createChangingUserProvider());
        $this->tokenProvider->createNewToken(self::createPersistentToken('wouter', 'series1', 'tokenvalue', new \DateTimeImmutable()));

        $this->expectException(NoSuchPropertyException::class);
        $handler->consumeRememberMeCookie(new RememberMeDetails('wouter', 360, 'series1:tokenvalue'));
    }

    public function testCreateRememberMeCookieWithAnExplicitPasswordTheUserDoesNotExpose()
    {
        $handler = $this->createHandler(['password'], $this->createPasswordlessUserProvider());

        $this->expectException(NoSuchPropertyException::class);
        $handler->createRememberMeCookie(new PasswordlessUser('wouter'));
    }

    public function testConsumeRememberMeCookieRejectsABoundTokenWhenThePropertyBecomesUnreadable()
    {
        $isReadable = true;
        $propertyAccessor = $this->createStub(PropertyAccessorInterface::class);
        $propertyAccessor->method('isReadable')->willReturnCallback(static function () use (&$isReadable) { return $isReadable; });
        $propertyAccessor->method('getValue')->willReturnCallback(fn () => $this->password);

        $handler = $this->createHandler(null, $this->createChangingUserProvider(), null, $propertyAccessor);
        $handler->createRememberMeCookie(new InMemoryUser('wouter', $this->password));

        [$series, $tokenValue] = $this->readCookieValue();

        $isReadable = false;

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('The cookie\'s hash is invalid.');
        $handler->consumeRememberMeCookie(new RememberMeDetails('wouter', 360, $series.':'.$tokenValue));
    }

    public function testConsumeRememberMeCookieWithUnchangedSignatureProperties()
    {
        $handler = $this->createSignedHandler();
        $handler->createRememberMeCookie(new InMemoryUser('wouter', $this->password));

        [$series, $tokenValue] = $this->readCookieValue();

        $this->assertStringContainsString('.', $tokenValue);
        $this->assertSame($tokenValue, $this->tokenProvider->loadTokenBySeries($series)->getTokenValue());

        $user = $handler->consumeRememberMeCookie(new RememberMeDetails('wouter', 360, $series.':'.$tokenValue));

        $this->assertSame('wouter', $user->getUserIdentifier());
    }

    public function testConsumeRememberMeCookieWithChangedSignatureProperties()
    {
        $handler = $this->createSignedHandler();
        $handler->createRememberMeCookie(new InMemoryUser('wouter', $this->password));

        [$series, $tokenValue] = $this->readCookieValue();
        $rememberMeDetails = new RememberMeDetails('wouter', 360, $series.':'.$tokenValue);
        $this->request->cookies->set('REMEMBERME', $rememberMeDetails->toString());

        $this->password = 'n3wp4ssw0rd';

        try {
            $handler->consumeRememberMeCookie($rememberMeDetails);
            $this->fail('The remember-me cookie should be rejected after a signature property changed.');
        } catch (AuthenticationException $e) {
            $this->assertSame('The cookie\'s hash is invalid.', $e->getMessage());
        }

        // the failed login clears the cookie, which is what deletes the token
        $handler->clearRememberMeCookie();

        $this->expectException(TokenNotFoundException::class);
        $this->tokenProvider->loadTokenBySeries($series);
    }

    public function testConsumeRememberMeCookieRejectsAnotherTokenValueAsTheft()
    {
        $handler = $this->createSignedHandler();
        $handler->createRememberMeCookie(new InMemoryUser('wouter', $this->password));

        [$series, $tokenValue] = $this->readCookieValue();
        [$random, $digest] = explode('.', $tokenValue);
        $random = substr_replace($random, 'a' === $random[0] ? 'b' : 'a', 0, 1);

        $this->expectException(CookieTheftException::class);
        $handler->consumeRememberMeCookie(new RememberMeDetails('wouter', 360, $series.':'.$random.'.'.$digest));
    }

    public function testConsumeRememberMeCookieBindsSignaturePropertiesToUnboundToken()
    {
        $handler = $this->createSignedHandler();
        $this->tokenProvider->createNewToken(self::createPersistentToken('wouter', 'series1', 'tokenvalue', new \DateTimeImmutable('-10 min')));

        $handler->consumeRememberMeCookie(new RememberMeDetails('wouter', 360, 'series1:tokenvalue'));

        [$series, $tokenValue] = $this->readCookieValue();
        $this->assertSame('series1', $series);
        $this->assertStringContainsString('.', $tokenValue);
        $this->assertSame($tokenValue, $this->tokenProvider->loadTokenBySeries('series1')->getTokenValue());

        $this->password = 'n3wp4ssw0rd';

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('The cookie\'s hash is invalid.');
        $handler->consumeRememberMeCookie(new RememberMeDetails('wouter', 360, $series.':'.$tokenValue));
    }

    public function testConsumeRememberMeCookieRejectsUnboundTokenValueAfterRotation()
    {
        $handler = $this->createSignedHandler();
        $this->tokenProvider->createNewToken(self::createPersistentToken('wouter', 'series1', 'tokenvalue', new \DateTimeImmutable('-10 min')));

        $handler->consumeRememberMeCookie(new RememberMeDetails('wouter', 360, 'series1:tokenvalue'));

        $this->expectException(CookieTheftException::class);
        $handler->consumeRememberMeCookie(new RememberMeDetails('wouter', 360, 'series1:tokenvalue'));
    }

    public function testConsumeRememberMeCookieAcceptsOutdatedTokenValue()
    {
        $handler = $this->createSignedHandler(new CacheTokenVerifier(new ArrayAdapter()));
        $handler->createRememberMeCookie(new InMemoryUser('wouter', $this->password));

        [$series, $tokenValue] = $this->readCookieValue();

        $token = $this->tokenProvider->loadTokenBySeries($series);
        $this->tokenProvider->createNewToken(self::createPersistentToken($token->getUserIdentifier(), $series, $token->getTokenValue(), new \DateTimeImmutable('-10 min')));

        $rememberMeDetails = new RememberMeDetails('wouter', 360, $series.':'.$tokenValue);
        $handler->consumeRememberMeCookie($rememberMeDetails);

        // a concurrent request still carries the previous token value
        $user = $handler->consumeRememberMeCookie($rememberMeDetails);

        $this->assertSame('wouter', $user->getUserIdentifier());
    }

    private function createSignedHandler(?TokenVerifierInterface $tokenVerifier = null): PersistentRememberMeHandler
    {
        return $this->createHandler(['password'], $this->createChangingUserProvider(), $tokenVerifier);
    }

    private function createHandler(?array $signatureProperties, UserProviderInterface $userProvider, ?TokenVerifierInterface $tokenVerifier = null, ?PropertyAccessorInterface $propertyAccessor = null): PersistentRememberMeHandler
    {
        return new PersistentRememberMeHandler($this->tokenProvider, $userProvider, $this->requestStack, [], null, $tokenVerifier, $signatureProperties, $propertyAccessor);
    }

    private function createChangingUserProvider(): UserProviderInterface
    {
        $userProvider = $this->createStub(UserProviderInterface::class);
        $userProvider->method('loadUserByIdentifier')->willReturnCallback(fn (string $identifier) => new InMemoryUser($identifier, $this->password));

        return $userProvider;
    }

    private function createPasswordlessUserProvider(): UserProviderInterface
    {
        $userProvider = $this->createStub(UserProviderInterface::class);
        $userProvider->method('loadUserByIdentifier')->willReturnCallback(fn (string $identifier) => new PasswordlessUser($identifier, $this->email));

        return $userProvider;
    }

    private function readCookieValue(): array
    {
        /** @var Cookie $cookie */
        $cookie = $this->request->attributes->get(ResponseListener::COOKIE_ATTR_NAME);

        return explode(':', explode(':', $cookie->getValue(), 4)[3], 2);
    }

    private static function createPersistentToken(string $userIdentifier, string $series, string $tokenValue, \DateTimeImmutable $lastUsed): PersistentToken
    {
        if (method_exists(PersistentToken::class, 'getClass')) {
            return new PersistentToken(InMemoryUser::class, $userIdentifier, $series, $tokenValue, $lastUsed, false);
        }

        return new PersistentToken($userIdentifier, $series, $tokenValue, $lastUsed);
    }
}
