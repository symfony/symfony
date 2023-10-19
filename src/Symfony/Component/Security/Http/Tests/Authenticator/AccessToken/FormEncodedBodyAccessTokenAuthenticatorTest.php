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
use Symfony\Component\Security\Http\AccessToken\FormEncodedBodyExtractor;
use Symfony\Component\Security\Http\Authenticator\AccessTokenAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Tests\Authenticator\InMemoryAccessTokenHandler;

class FormEncodedBodyAccessTokenAuthenticatorTest extends TestCase
{
    private InMemoryUserProvider $userProvider;
    private AccessTokenAuthenticator $authenticator;
    private AccessTokenHandlerInterface $accessTokenHandler;

    protected function setUp(): void
    {
        $this->userProvider = new InMemoryUserProvider();
        $this->accessTokenHandler = new InMemoryAccessTokenHandler();
    }

    public function testSupport()
    {
        $this->setUpAuthenticator();
        $request = new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        $request->request->set('access_token', 'INVALID_ACCESS_TOKEN');
        $request->setMethod(Request::METHOD_POST);

        $this->assertNull($this->authenticator->supports($request));
    }

    public function testSupportsWithCustomParameter()
    {
        $this->setUpAuthenticator('protection-token');
        $request = new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        $request->request->set('protection-token', 'INVALID_ACCESS_TOKEN');
        $request->setMethod(Request::METHOD_POST);

        $this->assertNull($this->authenticator->supports($request));
    }

    public function testAuthenticate()
    {
        $this->accessTokenHandler->add('VALID_ACCESS_TOKEN', new UserBadge('foo'));
        $this->setUpAuthenticator();
        $request = new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded'], 'access_token=VALID_ACCESS_TOKEN');
        $request->request->set('access_token', 'VALID_ACCESS_TOKEN');
        $request->setMethod(Request::METHOD_POST);

        $passport = $this->authenticator->authenticate($request);
        $this->assertInstanceOf(SelfValidatingPassport::class, $passport);
    }

    public function testAuthenticateWithCustomParameter()
    {
        $this->accessTokenHandler->add('VALID_ACCESS_TOKEN', new UserBadge('foo'));
        $this->setUpAuthenticator('protection-token');
        $request = new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        $request->request->set('protection-token', 'VALID_ACCESS_TOKEN');
        $request->setMethod(Request::METHOD_POST);

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
        $request->setMethod(Request::METHOD_GET);
        yield [$request, 'Invalid credentials.', BadCredentialsException::class];

        $request = new Request();
        $request->setMethod(Request::METHOD_POST);
        yield [$request, 'Invalid credentials.', BadCredentialsException::class];

        $request = new Request([], [], [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer VALID_ACCESS_TOKEN']);
        $request->setMethod(Request::METHOD_POST);
        yield [$request, 'Invalid credentials.', BadCredentialsException::class];

        $request = new Request();
        $request->setMethod(Request::METHOD_POST);
        $request->request->set('foo', 'VALID_ACCESS_TOKEN');
        yield [$request, 'Invalid credentials.', BadCredentialsException::class];

        $request = new Request([], [], [], [], [], ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']);
        $request->setMethod(Request::METHOD_POST);
        $request->request->set('access_token', 'INVALID_ACCESS_TOKEN');
        yield [$request, 'Invalid access token or invalid user.', BadCredentialsException::class];
    }

    private function setUpAuthenticator(string $parameter = 'access_token'): void
    {
        $this->authenticator = new AccessTokenAuthenticator(
            $this->accessTokenHandler,
            new FormEncodedBodyExtractor($parameter),
            $this->userProvider
        );
    }
}
