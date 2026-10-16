<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Authentication;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\DefaultAuthenticationFailureHandler;
use Symfony\Component\Security\Http\Authentication\DefaultAuthenticationSuccessHandler;
use Symfony\Component\Security\Http\HttpUtils;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

class DefaultAuthenticationFailureHandlerTest extends TestCase
{
    private Request $request;
    private SessionInterface $session;
    private AuthenticationException $exception;

    protected function setUp(): void
    {
        $this->session = new Session(new MockArraySessionStorage());
        $this->request = Request::create('https://localhost/');
        $this->request->attributes->replace(['_stateless' => false]);
        $this->request->setSession($this->session);
        $this->exception = new AuthenticationException();
    }

    #[DataProvider('provideLegacyDefaultOptions')]
    public function testSubclassWithLegacyDefaultOptions(array $options, ?string $expectedTarget)
    {
        $handler = new class($this->createStub(HttpKernelInterface::class), new HttpUtils(), $options, null, 'main') extends DefaultAuthenticationFailureHandler {
            protected array $defaultOptions = [
                'failure_path' => null,
                'failure_forward' => false,
                'login_path' => '/custom-login',
                'failure_path_parameter' => '_failure_path',
            ];
        };
        $this->request->headers->set('Referer', 'https://localhost/test');

        $response = $handler->onAuthenticationFailure($this->request, $this->exception);

        $this->assertSame('https://localhost/custom-login', $response->getTargetUrl());
        $this->assertSame($expectedTarget, $this->session->get('_security.main.target_path'));
    }

    public static function provideLegacyDefaultOptions(): iterable
    {
        yield 'missing use_referer' => [[], null];
        yield 'missing always_use_default_target_path' => [['use_referer' => true], 'https://localhost/test'];
    }

    public function testExplicitSuccessTargetPrecedesRetainedReferer()
    {
        $httpUtils = new HttpUtils();
        $handler = new DefaultAuthenticationFailureHandler($this->createStub(HttpKernelInterface::class), $httpUtils, ['use_referer' => true], null, 'main');
        $this->request->headers->set('Referer', 'https://localhost/test');
        $handler->onAuthenticationFailure($this->request, $this->exception);
        $this->request->query->set('_target_path', '/chosen');
        $successHandler = new DefaultAuthenticationSuccessHandler($httpUtils);
        $successHandler->setFirewallName('main');

        $response = $successHandler->onAuthenticationSuccess($this->request, $this->createStub(TokenInterface::class));

        $this->assertSame('https://localhost/chosen', $response->getTargetUrl());
        $this->assertSame('https://localhost/test', $this->session->get('_security.main.target_path'));
    }

    public function testRefererFromAnotherHostIsNotStored()
    {
        $handler = new DefaultAuthenticationFailureHandler($this->createStub(HttpKernelInterface::class), new HttpUtils(), ['use_referer' => true], null, 'main');

        foreach (['https://external.example/test', 'https://localhost.external.example/test', 'https://localhost:8443/test', '//external.example/test', 'https:\\\\external.example/test', 'javascript:alert(1)//localhost/'] as $referer) {
            $this->request->headers->set('Referer', $referer);
            $handler->onAuthenticationFailure($this->request, $this->exception);

            $this->assertFalse($this->session->has('_security.main.target_path'), $referer);
        }

        $this->request->headers->set('Referer', 'http://localhost/test');
        $handler->onAuthenticationFailure($this->request, $this->exception);

        $this->assertSame('http://localhost/test', $this->session->get('_security.main.target_path'));
    }

    public function testRefererIsRetainedAfterFailedLogin()
    {
        $httpUtils = new HttpUtils();
        $handler = new DefaultAuthenticationFailureHandler($this->createStub(HttpKernelInterface::class), $httpUtils, ['use_referer' => true], null, 'main');
        $this->request->headers->set('Referer', 'https://localhost/test?filter=value');
        $handler->onAuthenticationFailure($this->request, $this->exception);

        $this->request->headers->set('Referer', 'https://localhost/login');
        $handler->onAuthenticationFailure($this->request, $this->exception);
        $successHandler = new DefaultAuthenticationSuccessHandler($httpUtils, ['use_referer' => true]);
        $successHandler->setFirewallName('main');

        $response = $successHandler->onAuthenticationSuccess($this->request, $this->createStub(TokenInterface::class));

        $this->assertSame('https://localhost/test', $response->getTargetUrl());
        $this->assertFalse($this->session->has('_security.main.target_path'));
    }

    public function testRefererDoesNotReplaceStoredTargetPath()
    {
        $this->session->set('_security.main.target_path', 'https://localhost/protected');
        $this->request->headers->set('Referer', 'https://localhost/test');
        $handler = new DefaultAuthenticationFailureHandler($this->createStub(HttpKernelInterface::class), new HttpUtils(), ['use_referer' => true], null, 'main');
        $handler->onAuthenticationFailure($this->request, $this->exception);

        $this->assertSame('https://localhost/protected', $this->session->get('_security.main.target_path'));
    }

    public function testRefererIsNotStoredWhenDisabledOrStateless()
    {
        $this->request->headers->set('Referer', 'https://localhost/test');
        $kernel = $this->createStub(HttpKernelInterface::class);

        $handler = new DefaultAuthenticationFailureHandler($kernel, new HttpUtils(), [], null, 'main');
        $handler->onAuthenticationFailure($this->request, $this->exception);
        $this->assertFalse($this->session->has('_security.main.target_path'));

        $handler = new DefaultAuthenticationFailureHandler($kernel, new HttpUtils(), ['use_referer' => true, 'always_use_default_target_path' => true], null, 'main');
        $handler->onAuthenticationFailure($this->request, $this->exception);
        $this->assertFalse($this->session->has('_security.main.target_path'));

        $this->request->attributes->set('_stateless', true);
        $handler = new DefaultAuthenticationFailureHandler($kernel, new HttpUtils(), ['use_referer' => true], null, 'main');
        $handler->onAuthenticationFailure($this->request, $this->exception);
        $this->assertFalse($this->session->has('_security.main.target_path'));
    }

    public function testLoginRefererIsNotStored()
    {
        $this->request->headers->set('Referer', 'https://localhost/login?error=1');
        $handler = new DefaultAuthenticationFailureHandler($this->createStub(HttpKernelInterface::class), new HttpUtils(), ['use_referer' => true], null, 'main');
        $handler->onAuthenticationFailure($this->request, $this->exception);

        $this->assertFalse($this->session->has('_security.main.target_path'));
    }

    public function testCheckPathRefererIsNotStoredAfterForward()
    {
        $request = Request::create('https://localhost/login_check', 'POST');
        $request->setSession($this->session);
        $request->headers->set('Referer', 'https://localhost/login_check?error=1');
        $kernel = $this->createStub(HttpKernelInterface::class);
        $kernel->method('handle')->willReturn(new Response());
        $handler = new DefaultAuthenticationFailureHandler($kernel, new HttpUtils(), ['failure_forward' => true, 'use_referer' => true], null, 'main');
        $handler->onAuthenticationFailure($request, $this->exception);

        $this->assertFalse($this->session->has('_security.main.target_path'));
    }

    public function testForwardWithRefererAndNoSession()
    {
        $request = Request::create('https://localhost/login_check');
        $request->headers->set('Referer', 'https://localhost/test');
        $response = new Response();
        $kernel = $this->createStub(HttpKernelInterface::class);
        $kernel->method('handle')->willReturn($response);
        $handler = new DefaultAuthenticationFailureHandler($kernel, new HttpUtils(), ['failure_forward' => true, 'use_referer' => true], null, 'main');

        $this->assertSame($response, $handler->onAuthenticationFailure($request, $this->exception));
        $this->assertFalse($request->hasSession());
    }

    public function testForward()
    {
        $options = ['failure_forward' => true];

        $subRequest = Request::create('/');
        $httpUtils = $this->createMock(HttpUtils::class);
        $httpUtils->expects($this->once())
            ->method('createRequest')->with($this->request, '/login')
            ->willReturn($subRequest);

        $response = new Response();
        $httpKernel = $this->createStub(HttpKernelInterface::class);
        $httpKernel->method('handle')->willReturn($response);
        $handler = new DefaultAuthenticationFailureHandler($httpKernel, $httpUtils, $options, new NullLogger());
        $result = $handler->onAuthenticationFailure($this->request, $this->exception);

        $this->assertSame($response, $result);
        $this->assertSame($this->exception, $subRequest->attributes->get(SecurityRequestAttributes::AUTHENTICATION_ERROR));
    }

    public function testRedirect()
    {
        $handler = new DefaultAuthenticationFailureHandler($this->createStub(HttpKernelInterface::class), new HttpUtils(), [], new NullLogger());
        $result = $handler->onAuthenticationFailure($this->request, $this->exception);

        $this->assertSame('https://localhost/login', $result->getTargetUrl());
    }

    public function testExceptionIsPersistedInSession()
    {
        $handler = new DefaultAuthenticationFailureHandler($this->createStub(HttpKernelInterface::class), new HttpUtils(), [], new NullLogger());
        $handler->onAuthenticationFailure($this->request, $this->exception);

        $this->assertTrue($this->session->has(SecurityRequestAttributes::AUTHENTICATION_ERROR));
        $this->assertSame($this->exception, $this->session->get(SecurityRequestAttributes::AUTHENTICATION_ERROR));
    }

    public function testExceptionIsNotPersistedInSessionOnStatelessRequest()
    {
        $this->request->attributes->replace(['_stateless' => true]);

        $handler = new DefaultAuthenticationFailureHandler($this->createStub(HttpKernelInterface::class), new HttpUtils(), [], new NullLogger());
        $handler->onAuthenticationFailure($this->request, $this->exception);

        $this->assertFalse($this->session->has(SecurityRequestAttributes::AUTHENTICATION_ERROR));
    }

    public function testExceptionIsPassedInRequestOnForward()
    {
        $options = ['failure_forward' => true];

        $subRequest = Request::create('/');

        $httpUtils = $this->createMock(HttpUtils::class);
        $httpUtils->expects($this->once())
            ->method('createRequest')->with($this->request, '/login')
            ->willReturn($subRequest);

        $handler = new DefaultAuthenticationFailureHandler($this->createStub(HttpKernelInterface::class), $httpUtils, $options, new NullLogger());
        $handler->onAuthenticationFailure($this->request, $this->exception);

        $this->assertSame($this->exception, $subRequest->attributes->get(SecurityRequestAttributes::AUTHENTICATION_ERROR));
        $this->assertSame([], $this->session->all());
    }

    public function testRedirectIsLogged()
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('debug')
            ->with('Authentication failure, redirect triggered.', ['failure_path' => '/login']);

        $handler = new DefaultAuthenticationFailureHandler($this->createStub(HttpKernelInterface::class), new HttpUtils(), [], $logger);
        $handler->onAuthenticationFailure($this->request, $this->exception);
    }

    public function testForwardIsLogged()
    {
        $options = ['failure_forward' => true];

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('debug')
            ->with('Authentication failure, forward triggered.', ['failure_path' => '/login']);

        $handler = new DefaultAuthenticationFailureHandler($this->createStub(HttpKernelInterface::class), new HttpUtils(), $options, $logger);
        $handler->onAuthenticationFailure($this->request, $this->exception);
    }

    public function testFailurePathCanBeOverwritten()
    {
        $options = ['failure_path' => '/auth/login'];

        $handler = new DefaultAuthenticationFailureHandler($this->createStub(HttpKernelInterface::class), new HttpUtils(), $options, new NullLogger());
        $result = $handler->onAuthenticationFailure($this->request, $this->exception);

        $this->assertSame('https://localhost/auth/login', $result->getTargetUrl());
    }

    public function testFailurePathCanBeOverwrittenWithRequest()
    {
        $this->request->attributes->set('_failure_path', '/auth/login');

        $handler = new DefaultAuthenticationFailureHandler($this->createStub(HttpKernelInterface::class), new HttpUtils(), [], new NullLogger());
        $result = $handler->onAuthenticationFailure($this->request, $this->exception);

        $this->assertSame('https://localhost/auth/login', $result->getTargetUrl());
    }

    public function testFailurePathCanBeOverwrittenWithNestedAttributeInRequest()
    {
        $this->request->attributes->set('_failure_path', ['value' => '/auth/login']);

        $handler = new DefaultAuthenticationFailureHandler($this->createStub(HttpKernelInterface::class), new HttpUtils(), ['failure_path_parameter' => '_failure_path[value]'], new NullLogger());
        $result = $handler->onAuthenticationFailure($this->request, $this->exception);

        $this->assertSame('https://localhost/auth/login', $result->getTargetUrl());
    }

    public function testFailurePathParameterCanBeOverwritten()
    {
        $options = ['failure_path_parameter' => '_my_failure_path'];

        $this->request->attributes->set('_my_failure_path', '/auth/login');

        $handler = new DefaultAuthenticationFailureHandler($this->createStub(HttpKernelInterface::class), new HttpUtils(), $options, new NullLogger());
        $result = $handler->onAuthenticationFailure($this->request, $this->exception);

        $this->assertSame('https://localhost/auth/login', $result->getTargetUrl());
    }

    public function testFailurePathFromRequestWithInvalidUrl()
    {
        $options = ['failure_path_parameter' => '_my_failure_path'];

        $this->request->attributes->set('_my_failure_path', 'some_route_name');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))
            ->method('debug')
            ->willReturnCallback(function (...$args) {
                static $series = [
                    ['Ignoring query parameter "_my_failure_path": not a valid URL.', []],
                    ['Authentication failure, redirect triggered.', ['failure_path' => '/login']],
                ];

                $expectedArgs = array_shift($series);
                $this->assertSame($expectedArgs, $args);
            });

        $handler = new DefaultAuthenticationFailureHandler($this->createStub(HttpKernelInterface::class), new HttpUtils(), $options, $logger);

        $handler->onAuthenticationFailure($this->request, $this->exception);
    }

    public function testAbsoluteUrlRedirectionFromRequest()
    {
        $options = ['failure_path_parameter' => '_my_failure_path'];

        $this->request->attributes->set('_my_failure_path', 'https://localhost/some-path');

        $handler = new DefaultAuthenticationFailureHandler($this->createStub(HttpKernelInterface::class), new HttpUtils(), $options, new NullLogger());
        $result = $handler->onAuthenticationFailure($this->request, $this->exception);

        $this->assertSame('https://localhost/some-path', $result->getTargetUrl());
    }

    public function testFailurePathFromRequestIsIgnoredOnForward()
    {
        $options = ['failure_forward' => true];

        $this->request->attributes->set('_failure_path', '/admin/export-users');

        $subRequest = new Request();

        $httpUtils = $this->createMock(HttpUtils::class);
        $httpUtils->expects($this->once())
            ->method('createRequest')->with($this->request, '/login')
            ->willReturn($subRequest);

        $response = new Response();
        $httpKernel = $this->createStub(HttpKernelInterface::class);
        $httpKernel->method('handle')->willReturn($response);

        $handler = new DefaultAuthenticationFailureHandler($httpKernel, $httpUtils, $options, new NullLogger());
        $result = $handler->onAuthenticationFailure($this->request, $this->exception);

        $this->assertSame($response, $result);
        $this->assertSame($this->exception, $subRequest->attributes->get(SecurityRequestAttributes::AUTHENTICATION_ERROR));
    }

    public function testConfiguredFailurePathIsHonoredOnForward()
    {
        $options = ['failure_forward' => true, 'failure_path' => '/auth/login'];

        $this->request->attributes->set('_failure_path', '/admin/export-users');

        $subRequest = new Request();

        $httpUtils = $this->createMock(HttpUtils::class);
        $httpUtils->expects($this->once())
            ->method('createRequest')->with($this->request, '/auth/login')
            ->willReturn($subRequest);

        $handler = new DefaultAuthenticationFailureHandler($this->createStub(HttpKernelInterface::class), $httpUtils, $options, new NullLogger());
        $handler->onAuthenticationFailure($this->request, $this->exception);
    }
}
