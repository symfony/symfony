<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Authenticator\AccessToken;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\InMemoryUserProvider;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\AccessToken\ChainAccessTokenExtractor;
use Symfony\Component\Security\Http\AccessToken\FormEncodedBodyExtractor;
use Symfony\Component\Security\Http\AccessToken\HeaderAccessTokenExtractor;
use Symfony\Component\Security\Http\AccessToken\QueryAccessTokenExtractor;
use Symfony\Component\Security\Http\Authenticator\AccessTokenAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Tests\Authenticator\InMemoryAccessTokenHandler;

class ChainedAccessTokenExtractorsTest extends TestCase
{
    private InMemoryUserProvider $userProvider;
    private AccessTokenAuthenticator $authenticator;
    private AccessTokenHandlerInterface $accessTokenHandler;

    protected function setUp(): void
    {
        $this->userProvider = new InMemoryUserProvider();
        $this->accessTokenHandler = new InMemoryAccessTokenHandler();
    }

    /**
     * @dataProvider provideSupportData
     */
    public function testSupport($request)
    {
        $this->setUpAuthenticator();

        $this->assertNull($this->authenticator->supports($request));
    }

    public static function provideSupportData(): iterable
    {
        yield [new Request([], [], [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer VALID_ACCESS_TOKEN'])];
        yield [new Request([], [], [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer INVALID_ACCESS_TOKEN'])];
    }

    public function testAuthenticate()
    {
        $this->accessTokenHandler->add('VALID_ACCESS_TOKEN', new UserBadge('foo'));
        $this->setUpAuthenticator();

        $request = new Request([], [], [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer VALID_ACCESS_TOKEN']);
        $passport = $this->authenticator->authenticate($request);
        $this->assertInstanceOf(SelfValidatingPassport::class, $passport);
    }

    /**
     * @dataProvider provideInvalidAuthenticateData
     */
    public function testAuthenticateInvalid($request, $errorMessage, $exceptionType = BadRequestHttpException::class)
    {
        $this->expectException($exceptionType);
        $this->expectExceptionMessage($errorMessage);

        $this->setUpAuthenticator();

        $this->authenticator->authenticate($request);
    }

    public static function provideInvalidAuthenticateData(): iterable
    {
        $request = new Request();
        yield [$request, 'Invalid credentials.', BadCredentialsException::class];

        $request = new Request([], [], [], [], [], ['HTTP_AUTHORIZATION' => 'BAD']);
        yield [$request, 'Invalid credentials.', BadCredentialsException::class];

        $request = new Request([], [], [], [], [], ['HTTP_AUTHORIZATION' => 'JWT FOO']);
        yield [$request, 'Invalid credentials.', BadCredentialsException::class];

        $request = new Request([], [], [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer contains invalid characters such as whitespaces']);
        yield [$request, 'Invalid credentials.', BadCredentialsException::class];

        $request = new Request([], [], [], [], [], ['HTTP_AUTHORIZATION' => 'BearerVALID_ACCESS_TOKEN']);
        yield [$request, 'Invalid credentials.', BadCredentialsException::class];

        $request = new Request([], [], [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer INVALID_ACCESS_TOKEN']);
        yield [$request, 'Invalid access token or invalid user.', BadCredentialsException::class];
    }

    private function setUpAuthenticator(): void
    {
        $this->authenticator = new AccessTokenAuthenticator(
            $this->accessTokenHandler,
            new ChainAccessTokenExtractor([
                new FormEncodedBodyExtractor(),
                new QueryAccessTokenExtractor(),
                new HeaderAccessTokenExtractor(),
            ]),
            $this->userProvider
        );
    }
}
