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
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Core\User\InMemoryUserProvider;
use Symfony\Component\Security\Http\AccessToken\AccessTokenExtractorInterface;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\AccessToken\Dpop\Exception\InvalidDpopProofException;
use Symfony\Component\Security\Http\AccessToken\HeaderAccessTokenExtractor;
use Symfony\Component\Security\Http\AccessToken\SenderConstraintInterface;
use Symfony\Component\Security\Http\Authenticator\AccessTokenAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Debug\UnsupportedReasons;
use Symfony\Component\Security\Http\Authenticator\FallbackUserLoader;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

class AccessTokenAuthenticatorTest extends TestCase
{
    private InMemoryUserProvider $userProvider;

    protected function setUp(): void
    {
        $this->userProvider = new InMemoryUserProvider(['test' => ['password' => 's$cr$t']]);
    }

    public function testAuthenticateWithoutAccessToken()
    {
        $request = Request::create('/test');

        $accessTokenExtractor = $this->createMock(AccessTokenExtractorInterface::class);
        $accessTokenExtractor
            ->expects($this->once())
            ->method('extractAccessToken')
            ->with($request)
            ->willReturn(null);

        $authenticator = new AccessTokenAuthenticator(
            $this->createStub(AccessTokenHandlerInterface::class),
            $accessTokenExtractor,
        );

        $this->expectException(BadCredentialsException::class);
        $this->expectExceptionMessage('Invalid credentials.');

        $authenticator->authenticate($request);
    }

    public function testAuthenticateWithoutProvider()
    {
        $request = Request::create('/test');

        $accessTokenHandler = $this->createMock(AccessTokenHandlerInterface::class);
        $accessTokenExtractor = $this->createMock(AccessTokenExtractorInterface::class);
        $accessTokenExtractor
            ->expects($this->once())
            ->method('extractAccessToken')
            ->with($request)
            ->willReturn('test');
        $accessTokenHandler
            ->expects($this->once())
            ->method('getUserBadgeFrom')
            ->with('test')
            ->willReturn(new UserBadge('john', static fn () => new InMemoryUser('john', null)));

        $authenticator = new AccessTokenAuthenticator(
            $accessTokenHandler,
            $accessTokenExtractor,
            $this->userProvider,
        );

        $passport = $authenticator->authenticate($request);

        $this->assertEquals('john', $passport->getUser()->getUserIdentifier());
    }

    public function testAuthenticateWithoutUserLoader()
    {
        $request = Request::create('/test');

        $accessTokenHandler = $this->createMock(AccessTokenHandlerInterface::class);
        $accessTokenExtractor = $this->createMock(AccessTokenExtractorInterface::class);
        $accessTokenExtractor
            ->expects($this->once())
            ->method('extractAccessToken')
            ->with($request)
            ->willReturn('test');
        $accessTokenHandler
            ->expects($this->once())
            ->method('getUserBadgeFrom')
            ->with('test')
            ->willReturn(new UserBadge('test'));

        $authenticator = new AccessTokenAuthenticator(
            $accessTokenHandler,
            $accessTokenExtractor,
            $this->userProvider,
        );

        $passport = $authenticator->authenticate($request);

        $this->assertEquals('test', $passport->getUser()->getUserIdentifier());
    }

    public function testAuthenticateWithUserLoader()
    {
        $request = Request::create('/test');

        $accessTokenHandler = $this->createMock(AccessTokenHandlerInterface::class);
        $accessTokenExtractor = $this->createMock(AccessTokenExtractorInterface::class);
        $accessTokenExtractor
            ->expects($this->once())
            ->method('extractAccessToken')
            ->with($request)
            ->willReturn('test');
        $accessTokenHandler
            ->expects($this->once())
            ->method('getUserBadgeFrom')
            ->with('test')
            ->willReturn(new UserBadge('john', static fn () => new InMemoryUser('john', null)));

        $authenticator = new AccessTokenAuthenticator(
            $accessTokenHandler,
            $accessTokenExtractor,
            $this->userProvider,
        );

        $passport = $authenticator->authenticate($request);

        $this->assertEquals('john', $passport->getUser()->getUserIdentifier());
    }

    public function testAuthenticateWithFallbackUserLoader()
    {
        $request = Request::create('/test');

        $accessTokenHandler = $this->createMock(AccessTokenHandlerInterface::class);
        $accessTokenExtractor = $this->createMock(AccessTokenExtractorInterface::class);
        $accessTokenExtractor
            ->expects($this->once())
            ->method('extractAccessToken')
            ->with($request)
            ->willReturn('test');
        $accessTokenHandler
            ->expects($this->once())
            ->method('getUserBadgeFrom')
            ->with('test')
            ->willReturn(new UserBadge('test', new FallbackUserLoader(static fn () => new InMemoryUser('john', null))));

        $authenticator = new AccessTokenAuthenticator(
            $accessTokenHandler,
            $accessTokenExtractor,
            $this->userProvider,
        );

        $passport = $authenticator->authenticate($request);

        $this->assertEquals('test', $passport->getUser()->getUserIdentifier());
    }

    #[DataProvider('provideAccessTokenHeaderRegex')]
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

    public function testAuthenticateHeaderOfTheFailureResponse()
    {
        $authenticator = new AccessTokenAuthenticator(
            $this->createStub(AccessTokenHandlerInterface::class),
            new HeaderAccessTokenExtractor(),
            realm: 'My API',
        );

        $response = $authenticator->onAuthenticationFailure(Request::create('/test'), new BadCredentialsException());

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('Bearer realm="My API",error="invalid_token",error_description="Invalid credentials."', $response->headers->get('WWW-Authenticate'));
    }

    #[DataProvider('provideResourceMetadataUris')]
    public function testAuthenticateHeaderAdvertisesTheResourceMetadata(?string $resourceMetadataUri, string $expected)
    {
        $authenticator = new AccessTokenAuthenticator(
            $this->createStub(AccessTokenHandlerInterface::class),
            new HeaderAccessTokenExtractor(),
            resourceMetadataUri: $resourceMetadataUri,
        );

        $response = $authenticator->onAuthenticationFailure(Request::create('https://api.example.com/test'), new BadCredentialsException());

        $this->assertSame($expected, $response->headers->get('WWW-Authenticate'));
    }

    public static function provideResourceMetadataUris(): iterable
    {
        yield 'none configured' => [null, 'Bearer error="invalid_token",error_description="Invalid credentials."'];
        yield 'a path is resolved against the request' => ['/.well-known/oauth-protected-resource', 'Bearer error="invalid_token",error_description="Invalid credentials.",resource_metadata="https://api.example.com/.well-known/oauth-protected-resource"'];
        yield 'an absolute URL is advertised as is' => ['https://other.example.com/.well-known/oauth-protected-resource/api', 'Bearer error="invalid_token",error_description="Invalid credentials.",resource_metadata="https://other.example.com/.well-known/oauth-protected-resource/api"'];
    }

    public function testTheChallengeOfARequestCarryingNoTokenHoldsNoErrorCode()
    {
        $authenticator = new AccessTokenAuthenticator(
            $this->createStub(AccessTokenHandlerInterface::class),
            new HeaderAccessTokenExtractor(),
            realm: 'My API',
            resourceMetadataUri: '/.well-known/oauth-protected-resource',
        );

        $response = $authenticator->start(Request::create('https://api.example.com/foo'));

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('Bearer realm="My API",resource_metadata="https://api.example.com/.well-known/oauth-protected-resource"', $response->headers->get('WWW-Authenticate'));
    }

    public function testTheSenderConstraintIsCheckedWithTheTokenAndTheClaimsItWasRead()
    {
        $request = Request::create('/test');
        $request->headers->set('Authorization', 'DPoP an-access-token');

        $accessTokenHandler = $this->createStub(AccessTokenHandlerInterface::class);
        $accessTokenHandler
            ->method('getUserBadgeFrom')
            ->willReturn(new UserBadge('test', null, ['sub' => 'test', 'cnf' => ['jkt' => 'a-thumbprint']]));

        $senderConstraint = $this->createMock(SenderConstraintInterface::class);
        $senderConstraint
            ->expects($this->once())
            ->method('check')
            ->with($request, 'an-access-token', ['sub' => 'test', 'cnf' => ['jkt' => 'a-thumbprint']]);

        $authenticator = new AccessTokenAuthenticator(
            $accessTokenHandler,
            new HeaderAccessTokenExtractor('Authorization', 'DPoP'),
            $this->userProvider,
            null,
            null,
            null,
            null,
            $senderConstraint,
        );

        $authenticator->authenticate($request);
    }

    public function testTheChallengeOfAFirewallWithASenderConstraintNamesItsSchemeAndItsParameters()
    {
        $authenticator = new AccessTokenAuthenticator(
            $this->createStub(AccessTokenHandlerInterface::class),
            new HeaderAccessTokenExtractor('Authorization', 'DPoP'),
            null,
            null,
            null,
            'My API',
            null,
            $this->createDpopSenderConstraint(),
        );

        $response = $authenticator->onAuthenticationFailure(Request::create('/test'), new InvalidDpopProofException());

        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame('DPoP realm="My API",error="invalid_dpop_proof",error_description="Invalid credentials.",algs="ES256"', $response->headers->get('WWW-Authenticate'));
    }

    public function testTheChallengeOfARequestCarryingNoTokenNamesTheSchemeItIsPresentedUnder()
    {
        $authenticator = new AccessTokenAuthenticator(
            $this->createStub(AccessTokenHandlerInterface::class),
            new HeaderAccessTokenExtractor('Authorization', 'DPoP'),
            null,
            null,
            null,
            null,
            null,
            $this->createDpopSenderConstraint(),
        );

        $this->assertSame('DPoP algs="ES256"', $authenticator->start(Request::create('/test'))->headers->get('WWW-Authenticate'));
    }

    private function createDpopSenderConstraint(): SenderConstraintInterface
    {
        $senderConstraint = $this->createStub(SenderConstraintInterface::class);
        $senderConstraint
            ->method('getChallenge')
            ->willReturnCallback(static fn (?\Throwable $exception) => ['DPoP', $exception instanceof InvalidDpopProofException ? ['error' => 'invalid_dpop_proof', 'algs' => 'ES256'] : ['algs' => 'ES256']]);

        return $senderConstraint;
    }

    public function testTheChallengeOfAFirewallThatPinsNothingIsTheBareScheme()
    {
        $authenticator = new AccessTokenAuthenticator($this->createStub(AccessTokenHandlerInterface::class), new HeaderAccessTokenExtractor());

        $response = $authenticator->start(Request::create('https://api.example.com/foo'));

        $this->assertSame('Bearer', $response->headers->get('WWW-Authenticate'));
    }

    #[DataProvider('provideScopeClaims')]
    public function testTheGrantedScopesAreExposedAsATokenAttribute(array $claims, array $expectedScopes)
    {
        $accessTokenHandler = $this->createStub(AccessTokenHandlerInterface::class);
        $accessTokenHandler
            ->method('getUserBadgeFrom')
            ->willReturn(new UserBadge('john', static fn () => new InMemoryUser('john', null), $claims));

        $authenticator = new AccessTokenAuthenticator($accessTokenHandler, new HeaderAccessTokenExtractor());
        $passport = $authenticator->authenticate(Request::create('/test', server: ['HTTP_AUTHORIZATION' => 'Bearer VALID_ACCESS_TOKEN']));

        $this->assertSame($expectedScopes, $authenticator->createToken($passport, 'main')->getAttribute(AccessTokenAuthenticator::SCOPE_ATTRIBUTE));
    }

    public static function provideScopeClaims(): iterable
    {
        yield 'the space-delimited "scope" claim of RFC 6749 §3.3' => [['scope' => 'openid profile:read'], ['openid', 'profile:read']];
        yield 'a single scope' => [['scope' => 'openid'], ['openid']];
        yield 'scopes separated by more than one space' => [['scope' => " openid \t profile:read "], ['openid', 'profile:read']];
        yield 'a scope granted twice' => [['scope' => 'openid openid'], ['openid']];
        yield 'the "scope" claim as a list' => [['scope' => ['openid', 'profile:read']], ['openid', 'profile:read']];
        yield 'the "scp" claim some providers use instead' => [['scp' => ['openid', 'profile:read']], ['openid', 'profile:read']];
        yield '"scope" wins over "scp"' => [['scope' => 'openid', 'scp' => ['profile:read']], ['openid']];
        yield 'an empty "scope" falls back on "scp"' => [['scope' => '', 'scp' => 'profile:read'], ['profile:read']];
        yield 'no scope claim at all' => [['sub' => 'john'], []];
        yield 'a scope claim of an unexpected type' => [['scope' => 42], []];
        yield 'a list holding values that are not scopes' => [['scope' => ['openid', 42, null]], ['openid']];
    }

    public function testTheScopeAttributeIsSetOnATokenBuiltFromABadgeWithoutAttributes()
    {
        $accessTokenHandler = $this->createStub(AccessTokenHandlerInterface::class);
        $accessTokenHandler
            ->method('getUserBadgeFrom')
            ->willReturn(new UserBadge('john', static fn () => new InMemoryUser('john', null)));

        $authenticator = new AccessTokenAuthenticator($accessTokenHandler, new HeaderAccessTokenExtractor());
        $passport = $authenticator->authenticate(Request::create('/test', server: ['HTTP_AUTHORIZATION' => 'Bearer VALID_ACCESS_TOKEN']));

        $this->assertSame([], $authenticator->createToken($passport, 'main')->getAttribute(AccessTokenAuthenticator::SCOPE_ATTRIBUTE));
    }

    public function testUnsupportedReasons()
    {
        $authenticator = new AccessTokenAuthenticator($this->createStub(AccessTokenHandlerInterface::class), new HeaderAccessTokenExtractor());

        $request = Request::create('/test');
        $request->attributes->set(SecurityRequestAttributes::UNSUPPORTED_REASONS, $reasons = new UnsupportedReasons());

        $this->assertFalse($authenticator->supports($request));
        $this->assertSame([\sprintf('the "%s" extractor found no access token in the request', HeaderAccessTokenExtractor::class)], $reasons->all());

        $request = Request::create('/test', server: ['HTTP_AUTHORIZATION' => 'Bearer VALID_ACCESS_TOKEN']);
        $request->attributes->set(SecurityRequestAttributes::UNSUPPORTED_REASONS, $reasons = new UnsupportedReasons());

        $this->assertNull($authenticator->supports($request));
        $this->assertSame([], $reasons->all());
    }
}
