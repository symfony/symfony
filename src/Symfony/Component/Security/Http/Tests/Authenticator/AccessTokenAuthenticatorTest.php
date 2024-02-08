<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Authenticator;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\InMemoryUserProvider;
use Symfony\Component\Security\Http\AccessToken\AccessTokenExtractorInterface;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\AccessTokenAuthenticator;
use Symfony\Component\Security\Http\Authenticator\FallbackUserLoader;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

class AccessTokenAuthenticatorTest extends TestCase
{
    private AccessTokenHandlerInterface $accessTokenHandler;
    private AccessTokenExtractorInterface $accessTokenExtractor;
    private InMemoryUserProvider $userProvider;

    protected function setUp(): void
    {
        $this->accessTokenHandler = $this->createMock(AccessTokenHandlerInterface::class);
        $this->accessTokenExtractor = $this->createMock(AccessTokenExtractorInterface::class);
        $this->userProvider = new InMemoryUserProvider(['test' => ['password' => 's$cr$t']]);
    }

    public function testAuthenticateWithoutAccessToken()
    {
        $request = Request::create('/test');

        $this->accessTokenExtractor
            ->expects($this->once())
            ->method('extractAccessToken')
            ->with($request)
            ->willReturn(null);

        $authenticator = new AccessTokenAuthenticator(
            $this->accessTokenHandler,
            $this->accessTokenExtractor,
        );

        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('Invalid credentials.');

        $authenticator->authenticate($request);
    }

    public function testAuthenticateWithoutProvider()
    {
        $request = Request::create('/test');

        $this->accessTokenExtractor
            ->expects($this->once())
            ->method('extractAccessToken')
            ->with($request)
            ->willReturn('test');
        $this->accessTokenHandler
            ->expects($this->once())
            ->method('getUserBadgeFrom')
            ->with('test')
            ->willReturn(new UserBadge('john', fn () => new InMemoryUser('john', null)));

        $authenticator = new AccessTokenAuthenticator(
            $this->accessTokenHandler,
            $this->accessTokenExtractor,
            $this->userProvider,
        );

        $passport = $authenticator->authenticate($request);

        $this->assertEquals('john', $passport->getUser()->getUserIdentifier());
    }

    public function testAuthenticateWithoutUserLoader()
    {
        $request = Request::create('/test');

        $this->accessTokenExtractor
            ->expects($this->once())
            ->method('extractAccessToken')
            ->with($request)
            ->willReturn('test');
        $this->accessTokenHandler
            ->expects($this->once())
            ->method('getUserBadgeFrom')
            ->with('test')
            ->willReturn(new UserBadge('test'));

        $authenticator = new AccessTokenAuthenticator(
            $this->accessTokenHandler,
            $this->accessTokenExtractor,
            $this->userProvider,
        );

        $passport = $authenticator->authenticate($request);

        $this->assertEquals('test', $passport->getUser()->getUserIdentifier());
    }

    public function testAuthenticateWithUserLoader()
    {
        $request = Request::create('/test');

        $this->accessTokenExtractor
            ->expects($this->once())
            ->method('extractAccessToken')
            ->with($request)
            ->willReturn('test');
        $this->accessTokenHandler
            ->expects($this->once())
            ->method('getUserBadgeFrom')
            ->with('test')
            ->willReturn(new UserBadge('john', fn () => new InMemoryUser('john', null)));

        $authenticator = new AccessTokenAuthenticator(
            $this->accessTokenHandler,
            $this->accessTokenExtractor,
            $this->userProvider,
        );

        $passport = $authenticator->authenticate($request);

        $this->assertEquals('john', $passport->getUser()->getUserIdentifier());
    }

    public function testAuthenticateWithFallbackUserLoader()
    {
        $request = Request::create('/test');

        $this->accessTokenExtractor
            ->expects($this->once())
            ->method('extractAccessToken')
            ->with($request)
            ->willReturn('test');
        $this->accessTokenHandler
            ->expects($this->once())
            ->method('getUserBadgeFrom')
            ->with('test')
            ->willReturn(new UserBadge('test', new FallbackUserLoader(fn () => new InMemoryUser('john', null))));

        $authenticator = new AccessTokenAuthenticator(
            $this->accessTokenHandler,
            $this->accessTokenExtractor,
            $this->userProvider,
        );

        $passport = $authenticator->authenticate($request);

        $this->assertEquals('test', $passport->getUser()->getUserIdentifier());
    }
}
