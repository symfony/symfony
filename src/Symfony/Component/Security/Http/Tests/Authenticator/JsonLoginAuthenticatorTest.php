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

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\InMemoryUserProvider;
use Symfony\Component\Security\Http\Authenticator\JsonLoginAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

class JsonLoginAuthenticatorTest extends TestCase
{
    use ExpectDeprecationTrait;

    private $userProvider;
    /** @var JsonLoginAuthenticator */
    private $authenticator;

    protected function setUp(): void
    {
        $this->userProvider = new InMemoryUserProvider();
    }

    /**
     * @dataProvider provideSupportData
     */
    public function testSupport($request)
    {
        $this->setUpAuthenticator();

        $this->assertTrue($this->authenticator->supports($request));
    }

    public static function provideSupportData()
    {
        yield [new Request([], [], [], [], [], ['HTTP_CONTENT_TYPE' => 'application/json'], '{"username": "dunglas", "password": "foo"}')];

        $request = new Request([], [], [], [], [], [], '{"username": "dunglas", "password": "foo"}');
        $request->setRequestFormat('json-ld');
        yield [$request];
    }

    /**
     * @dataProvider provideSupportsWithCheckPathData
     */
    public function testSupportsWithCheckPath($request, $result)
    {
        $this->setUpAuthenticator(['check_path' => '/api/login']);

        $this->assertSame($result, $this->authenticator->supports($request));
    }

    public static function provideSupportsWithCheckPathData()
    {
        yield [Request::create('/api/login', 'GET', [], [], [], ['HTTP_CONTENT_TYPE' => 'application/json']), true];
        yield [Request::create('/login', 'GET', [], [], [], ['HTTP_CONTENT_TYPE' => 'application/json']), false];
    }

    public function testAuthenticate()
    {
        $this->setUpAuthenticator();

        $request = new Request([], [], [], [], [], ['HTTP_CONTENT_TYPE' => 'application/json'], '{"username": "dunglas", "password": "foo"}');
        $passport = $this->authenticator->authenticate($request);
        $this->assertEquals('foo', $passport->getBadge(PasswordCredentials::class)->getPassword());
    }

    public function testAuthenticateWithCustomPath()
    {
        $this->setUpAuthenticator([
            'username_path' => 'authentication.username',
            'password_path' => 'authentication.password',
        ]);

        $request = new Request([], [], [], [], [], ['HTTP_CONTENT_TYPE' => 'application/json'], '{"authentication": {"username": "dunglas", "password": "foo"}}');
        $passport = $this->authenticator->authenticate($request);
        $this->assertEquals('foo', $passport->getBadge(PasswordCredentials::class)->getPassword());
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

    public static function provideInvalidAuthenticateData()
    {
        $request = new Request([], [], [], [], [], ['HTTP_CONTENT_TYPE' => 'application/json']);
        yield [$request, 'Invalid JSON.'];

        $request = new Request([], [], [], [], [], ['HTTP_CONTENT_TYPE' => 'application/json'], '{"usr": "dunglas", "password": "foo"}');
        yield [$request, 'The key "username" must be provided'];

        $request = new Request([], [], [], [], [], ['HTTP_CONTENT_TYPE' => 'application/json'], '{"username": "dunglas", "pass": "foo"}');
        yield [$request, 'The key "password" must be provided'];

        $request = new Request([], [], [], [], [], ['HTTP_CONTENT_TYPE' => 'application/json'], '{"username": 1, "password": "foo"}');
        yield [$request, 'The key "username" must be a string.'];

        $request = new Request([], [], [], [], [], ['HTTP_CONTENT_TYPE' => 'application/json'], '{"username": "dunglas", "password": 1}');
        yield [$request, 'The key "password" must be a string.'];

        $username = str_repeat('x', UserBadge::MAX_USERNAME_LENGTH + 1);
        $request = new Request([], [], [], [], [], ['HTTP_CONTENT_TYPE' => 'application/json'], sprintf('{"username": "%s", "password": "foo"}', $username));
        yield [$request, 'Username too long.', BadCredentialsException::class];
    }

    /**
     * @dataProvider provideEmptyAuthenticateData
     *
     * @group legacy
     */
    public function testAuthenticationForEmptyCredentialDeprecation($request)
    {
        $this->expectDeprecation('Since symfony/security 6.2: Passing an empty string as username or password parameter is deprecated.');
        $this->setUpAuthenticator();

        $this->authenticator->authenticate($request);
    }

    public static function provideEmptyAuthenticateData()
    {
        $request = new Request([], [], [], [], [], ['HTTP_CONTENT_TYPE' => 'application/json'], '{"username": "", "password": "notempty"}');
        yield [$request];
    }

    public function testAuthenticationFailureWithoutTranslator()
    {
        $this->setUpAuthenticator();

        $response = $this->authenticator->onAuthenticationFailure(new Request(), new AuthenticationException());
        $this->assertSame(['error' => 'An authentication exception occurred.'], json_decode($response->getContent(), true));
    }

    public function testAuthenticationFailureWithTranslator()
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['An authentication exception occurred.' => 'foo'], 'en', 'security');

        $this->setUpAuthenticator();
        $this->authenticator->setTranslator($translator);

        $response = $this->authenticator->onAuthenticationFailure(new Request(), new AuthenticationException());
        $this->assertSame(['error' => 'foo'], json_decode($response->getContent(), true));
    }

    public function testOnFailureReplacesMessageDataWithoutTranslator()
    {
        $this->setUpAuthenticator();

        $response = $this->authenticator->onAuthenticationFailure(new Request(), new class() extends AuthenticationException {
            public function getMessageData(): array
            {
                return ['%failed_attempts%' => 3];
            }

            public function getMessageKey(): string
            {
                return 'Session locked after %failed_attempts% failed attempts.';
            }
        });

        $this->assertSame(['error' => 'Session locked after 3 failed attempts.'], json_decode($response->getContent(), true));
    }

    private function setUpAuthenticator(array $options = [])
    {
        $this->authenticator = new JsonLoginAuthenticator(new HttpUtils(), $this->userProvider, null, null, $options);
    }
}
