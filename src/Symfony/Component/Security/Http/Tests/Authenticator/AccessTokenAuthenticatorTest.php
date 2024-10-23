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
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\InMemoryUserProvider;
use Symfony\Component\Security\Http\AccessToken\AccessTokenExtractorInterface;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\AccessToken\HeaderAccessTokenExtractor;
use Symfony\Component\Security\Http\Authenticator\AccessTokenAuthenticator;
use Symfony\Component\Security\Http\Authenticator\FallbackUserLoader;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Contracts\Translation\TranslatorInterface;

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

    public function testOnAuthenticationFailureWithTranslatorTranslatesErrorMessage()
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
            $this->userProvider,
        );

        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->expects($this->once())
            ->method('trans')
            ->with('Invalid credentials.')
            ->willReturn('Credenciales invalidas.');

        $authenticator->setTranslator($translator);

        $response = null;
        try {
            $authenticator->authenticate($request);
        } catch (BadCredentialsException $e) {
            $response = $authenticator->onAuthenticationFailure($request, $e);
        }
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('Bearer error="invalid_token",error_description="Credenciales invalidas."', $response->headers->get('WWW-Authenticate'));
    }

    /**
     * @group legacy
     *
     * @expectedDeprecation Since symfony/security-http 6.4: Using non-ASCII characters in the error message is deprecated. Use ASCII characters only.
     */
    public function testOnAuthenticationFailureWithTranslatorThrowsDeprecationWhenTranslatedMessageContainsNonAscii()
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
            $this->userProvider,
        );

        $nonAsciiString = 'Credenciales inválidas.';
        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->expects($this->once())
            ->method('trans')
            ->with('Invalid credentials.')
            ->willReturn($nonAsciiString);

        $authenticator->setTranslator($translator);

        $response = null;
        try {
            $authenticator->authenticate($request);
        } catch (BadCredentialsException $e) {
            $response = $authenticator->onAuthenticationFailure($request, $e);
        }
        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame('Bearer error="invalid_token",error_description="Credenciales inválidas."', $response->headers->get('WWW-Authenticate'));
    }

    public function testAuthenticateWithoutAccessToken()
    {
        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('Invalid credentials.');

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

    /**
     * @dataProvider provideAccessTokenHeaderRegex
     */
    public function testAccessTokenHeaderRegex(string $input, ?string $expectedToken)
    {
        // Given
        $extractor = new HeaderAccessTokenExtractor();
        $request = Request::create('/test', 'GET', [], [], [], ['HTTP_AUTHORIZATION' => $input]);

        // When
        $token = $extractor->extractAccessToken($request);

        // Then
        $this->assertEquals($expectedToken, $token);
    }

    public static function provideAccessTokenHeaderRegex(): array
    {
        return [
            ['Bearer token', 'token'],
            ['Bearer mF_9.B5f-4.1JqM', 'mF_9.B5f-4.1JqM'],
            ['Bearer d3JvbmdfcmVnZXhwX2V4bWFwbGU=', 'd3JvbmdfcmVnZXhwX2V4bWFwbGU='],
            ['Bearer Not Valid', null],
            ['Bearer (NotOK123)', null],
        ];
    }
}
