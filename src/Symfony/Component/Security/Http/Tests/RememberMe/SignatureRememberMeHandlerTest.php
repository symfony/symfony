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
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Signature\SignatureHasher;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\InMemoryUserProvider;
use Symfony\Component\Security\Http\RememberMe\RememberMeDetails;
use Symfony\Component\Security\Http\RememberMe\ResponseListener;
use Symfony\Component\Security\Http\RememberMe\SignatureRememberMeHandler;

class SignatureRememberMeHandlerTest extends TestCase
{
    private $signatureHasher;
    private $userProvider;
    private $request;
    private $requestStack;
    private $handler;

    protected function setUp(): void
    {
        $this->signatureHasher = new SignatureHasher(PropertyAccess::createPropertyAccessor(), [], 's3cret');
        $this->userProvider = new InMemoryUserProvider();
        $this->requestStack = new RequestStack();
        $this->request = Request::create('/login');
        $this->requestStack->push($this->request);
        $this->handler = new SignatureRememberMeHandler($this->signatureHasher, $this->userProvider, $this->requestStack, []);
    }

    /**
     * @group time-sensitive
     */
    public function testCreateRememberMeCookie()
    {
        $user = new InMemoryUser('wouter', null);
        $signature = $this->signatureHasher->computeSignatureHash($user, $expire = time() + 31536000);
        $this->userProvider->createUser(new InMemoryUser('wouter', null));

        $this->handler->createRememberMeCookie($user);

        $this->assertTrue($this->request->attributes->has(ResponseListener::COOKIE_ATTR_NAME));

        /** @var Cookie $cookie */
        $cookie = $this->request->attributes->get(ResponseListener::COOKIE_ATTR_NAME);
        $this->assertEquals(strtr(InMemoryUser::class, '\\', '.').':d291dGVy:'.$expire.':'.$signature, $cookie->getValue());
    }

    public function testClearRememberMeCookie()
    {
        $this->handler->clearRememberMeCookie();

        $this->assertTrue($this->request->attributes->has(ResponseListener::COOKIE_ATTR_NAME));

        /** @var Cookie $cookie */
        $cookie = $this->request->attributes->get(ResponseListener::COOKIE_ATTR_NAME);
        $this->assertNull($cookie->getValue());
    }

    public function testConsumeRememberMeCookieValid()
    {
        $user = new InMemoryUser('wouter', null);
        $signature = $this->signatureHasher->computeSignatureHash($user, $expire = time() + 3600);
        $this->userProvider->createUser(new InMemoryUser('wouter', null));

        $rememberMeDetails = new RememberMeDetails(InMemoryUser::class, 'wouter', $expire, $signature);
        $this->handler->consumeRememberMeCookie($rememberMeDetails);

        $this->assertTrue($this->request->attributes->has(ResponseListener::COOKIE_ATTR_NAME));

        /** @var Cookie $cookie */
        $cookie = $this->request->attributes->get(ResponseListener::COOKIE_ATTR_NAME);
        $this->assertNotEquals((new RememberMeDetails(InMemoryUser::class, 'wouter', $expire, $signature))->toString(), $cookie->getValue());
    }

    public function testConsumeRememberMeCookieInvalidHash()
    {
        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('The cookie\'s hash is invalid.');
        $this->handler->consumeRememberMeCookie(new RememberMeDetails(InMemoryUser::class, 'wouter', time() + 600, 'badsignature'));
    }

    public function testConsumeRememberMeCookieExpired()
    {
        $user = new InMemoryUser('wouter', null);
        $signature = $this->signatureHasher->computeSignatureHash($user, 360);

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('The cookie has expired.');
        $this->handler->consumeRememberMeCookie(new RememberMeDetails(InMemoryUser::class, 'wouter', 360, $signature));
    }
}
