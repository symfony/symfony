<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Security\Http\Tests\Firewall;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\AuthenticationTrustResolverInterface;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\LogoutException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\EntryPoint\FallbackAuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\EntryPoint\ReAuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\Firewall\ExceptionListener;
use Symfony\Component\Security\Http\HttpUtils;

class ExceptionListenerTest extends TestCase
{
    #[DataProvider('getAuthenticationExceptionProvider')]
    public function testAuthenticationExceptionWithoutEntryPoint(\Exception $exception, \Exception $eventException)
    {
        $event = $this->createEvent($exception);

        $listener = $this->createExceptionListener();
        $listener->onKernelException($event);

        $this->assertNull($event->getResponse());
        $this->assertEquals($eventException, $event->getThrowable());
    }

    #[DataProvider('getAuthenticationExceptionProvider')]
    public function testAuthenticationExceptionWithEntryPoint(\Exception $exception, \Exception $eventException)
    {
        $event = $this->createEvent($exception);

        $response = new Response('Forbidden', 403);

        $listener = $this->createExceptionListener(null, null, null, $this->createEntryPoint($response));
        $listener->onKernelException($event);

        $this->assertTrue($event->isAllowingCustomResponseCode());

        $this->assertEquals('Forbidden', $event->getResponse()->getContent());
        $this->assertEquals(403, $event->getResponse()->getStatusCode());
        $this->assertSame($exception, $event->getThrowable());
    }

    public static function getAuthenticationExceptionProvider()
    {
        return [
            [$e = new AuthenticationException(), new HttpException(Response::HTTP_UNAUTHORIZED, '', $e, [], 0)],
            [new \LogicException('random', 0, $e = new AuthenticationException()), new HttpException(Response::HTTP_UNAUTHORIZED, '', $e, [], 0)],
            [new \LogicException('random', 0, $e = new AuthenticationException('embed', 0, new AuthenticationException())), new HttpException(Response::HTTP_UNAUTHORIZED, 'embed', $e, [], 0)],
            [new \LogicException('random', 0, $e = new AuthenticationException('embed', 0, new AccessDeniedException())), new HttpException(Response::HTTP_UNAUTHORIZED, 'embed', $e, [], 0)],
            [$e = new AuthenticationException('random', 0, new \LogicException()), new HttpException(Response::HTTP_UNAUTHORIZED, 'random', $e, [], 0)],
        ];
    }

    #[DataProvider('getAccessDeniedExceptionProvider')]
    public function testAccessDeniedExceptionFullFledgedAndWithoutAccessDeniedHandlerAndWithoutErrorPage(\Exception $exception, ?\Exception $eventException = null)
    {
        $event = $this->createEvent($exception);

        $listener = $this->createExceptionListener(null, $this->createTrustResolver(true));
        $listener->onKernelException($event);

        $this->assertNull($event->getResponse());
        $this->assertSame($eventException ?? $exception, $event->getThrowable()->getPrevious());
    }

    #[DataProvider('getAccessDeniedExceptionProvider')]
    public function testAccessDeniedExceptionFullFledgedAndWithoutAccessDeniedHandlerAndWithErrorPage(\Exception $exception, ?\Exception $eventException = null)
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $kernel->method('handle')->willReturn(new Response('Unauthorized', 401));

        $event = $this->createEvent($exception, $kernel);

        $httpUtils = $this->createMock(HttpUtils::class);
        $httpUtils->expects($this->once())->method('createRequest')->willReturn(Request::create('/error'));

        $listener = $this->createExceptionListener(null, $this->createTrustResolver(true), $httpUtils, null, '/error');
        $listener->onKernelException($event);

        $this->assertTrue($event->isAllowingCustomResponseCode());

        $this->assertEquals('Unauthorized', $event->getResponse()->getContent());
        $this->assertEquals(401, $event->getResponse()->getStatusCode());
        $this->assertSame($eventException ?? $exception, $event->getThrowable()->getPrevious());
    }

    #[DataProvider('getAccessDeniedExceptionProvider')]
    public function testAccessDeniedExceptionFullFledgedAndWithAccessDeniedHandlerAndWithoutErrorPage(\Exception $exception, ?\Exception $eventException = null)
    {
        $event = $this->createEvent($exception);

        $accessDeniedHandler = $this->createMock(AccessDeniedHandlerInterface::class);
        $accessDeniedHandler->expects($this->once())->method('handle')->willReturn(new Response('error'));

        $listener = $this->createExceptionListener(null, $this->createTrustResolver(true), null, null, null, $accessDeniedHandler);
        $listener->onKernelException($event);

        $this->assertEquals('error', $event->getResponse()->getContent());
        $this->assertSame($eventException ?? $exception, $event->getThrowable()->getPrevious());
    }

    #[DataProvider('getAccessDeniedExceptionProvider')]
    public function testAccessDeniedExceptionNotFullFledged(\Exception $exception, ?\Exception $eventException = null)
    {
        $event = $this->createEvent($exception);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->expects($this->once())->method('getToken')->willReturn(new NullToken());

        $listener = $this->createExceptionListener($tokenStorage, $this->createTrustResolver(false), null, $this->createEntryPoint());
        $listener->onKernelException($event);

        $this->assertEquals('OK', $event->getResponse()->getContent());
        $this->assertSame($eventException ?? $exception, $event->getThrowable()->getPrevious());
    }

    public function testLogoutException()
    {
        $event = $this->createEvent(new LogoutException('Invalid CSRF.'));

        $listener = $this->createExceptionListener();
        $listener->onKernelException($event);

        $this->assertEquals('Invalid CSRF.', $event->getThrowable()->getMessage());
        $this->assertEquals(403, $event->getThrowable()->getStatusCode());
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testRegisterIsDeprecated()
    {
        $listener = $this->createExceptionListener();
        $dispatcher = new EventDispatcher();

        $this->expectUserDeprecationMessage('Since symfony/security-http 8.2: The "Symfony\\Component\\Security\\Http\\Firewall\\ExceptionListener::register()" method is deprecated and will be removed in 9.0, register "onKernelException()" on the "kernel.exception" event instead.');

        $listener->register($dispatcher);

        $this->assertNotEmpty($dispatcher->getListeners());
    }

    #[Group('legacy')]
    #[IgnoreDeprecations]
    public function testUnregisterIsDeprecated()
    {
        $listener = $this->createExceptionListener();
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(KernelEvents::EXCEPTION, $listener->onKernelException(...), 1);

        $this->expectUserDeprecationMessage('Since symfony/security-http 8.2: The "Symfony\\Component\\Security\\Http\\Firewall\\ExceptionListener::unregister()" method is deprecated and will be removed in 9.0, register "onKernelException()" on the "kernel.exception" event instead.');

        $listener->unregister($dispatcher);

        $this->assertSame([], $dispatcher->getListeners());
    }

    public static function getAccessDeniedExceptionProvider()
    {
        return [
            [new AccessDeniedException()],
            [new \LogicException('random', 0, $e = new AccessDeniedException()), $e],
            [new \LogicException('random', 0, $e = new AccessDeniedException('embed', new AccessDeniedException())), $e],
            [new \LogicException('random', 0, $e = new AccessDeniedException('embed', new AuthenticationException())), $e],
            [new AccessDeniedException('random', new \LogicException())],
        ];
    }

    public function testTargetPathIsSavedForAnEntryPointThatStartsAnAuthentication()
    {
        $event = $this->createEvent(new AuthenticationException(), null, $session = new Session(new MockArraySessionStorage()));

        $this->createExceptionListener(null, null, null, $this->createEntryPoint())->onKernelException($event);

        $this->assertSame('http://localhost/', $session->get('_security.key.target_path'));
    }

    public function testNoTargetPathIsSavedForAnEntryPointThatOnlyStandsIn()
    {
        $entryPoint = $this->createMock(FallbackAuthenticationEntryPointInterface::class);
        $entryPoint->expects($this->once())->method('start')->willReturn(new Response(null, 401));

        $event = $this->createEvent(new AuthenticationException(), null, $session = new Session(new MockArraySessionStorage()));

        $this->createExceptionListener(null, null, null, $entryPoint)->onKernelException($event);

        $this->assertFalse($session->isStarted());
        $this->assertNull($session->get('_security.key.target_path'));
    }

    private function createEntryPoint(?Response $response = null)
    {
        $entryPoint = $this->createMock(AuthenticationEntryPointInterface::class);
        $entryPoint->expects($this->once())->method('start')->willReturn($response ?? new Response('OK'));

        return $entryPoint;
    }

    private function createTrustResolver($fullFledged)
    {
        $trustResolver = $this->createMock(AuthenticationTrustResolverInterface::class);
        $trustResolver->expects($this->once())->method('isFullFledged')->willReturn($fullFledged);

        return $trustResolver;
    }

    private function createEvent(\Exception $exception, $kernel = null, ?Session $session = null)
    {
        $kernel ??= $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/');
        if (null !== $session) {
            $request->setSession($session);
        }

        return new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);
    }

    private function createExceptionListener(?TokenStorageInterface $tokenStorage = null, ?AuthenticationTrustResolverInterface $trustResolver = null, ?HttpUtils $httpUtils = null, ?AuthenticationEntryPointInterface $authenticationEntryPoint = null, $errorPage = null, ?AccessDeniedHandlerInterface $accessDeniedHandler = null, ?ReAuthenticationEntryPointInterface $reAuthenticationEntryPoint = null)
    {
        return new ExceptionListener(
            $tokenStorage ?? new TokenStorage(),
            $trustResolver ?? $this->createStub(AuthenticationTrustResolverInterface::class),
            $httpUtils ?? new HttpUtils(),
            'key',
            $authenticationEntryPoint,
            $errorPage,
            $accessDeniedHandler,
            null,
            false,
            $reAuthenticationEntryPoint
        );
    }

    private function createFullFledgedTrustResolver(): AuthenticationTrustResolverInterface
    {
        $trustResolver = $this->createMock(AuthenticationTrustResolverInterface::class);
        $trustResolver->expects($this->once())->method('isFullFledged')->willReturn(true);

        return $trustResolver;
    }

    private function createTokenStorageWithAToken(): TokenStorageInterface
    {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken(new InMemoryUser('wouter', 'password', ['ROLE_USER']), 'key', ['ROLE_USER']));

        return $tokenStorage;
    }

    public function testReAuthenticationEntryPointStartsOnAStaleAuthentication()
    {
        $exception = new AccessDeniedException();
        $exception->setAttributes([AuthenticatedVoter::IS_AUTHENTICATED_RECENTLY]);
        $event = $this->createEvent($exception);

        $entryPoint = $this->createMock(ReAuthenticationEntryPointInterface::class);
        $entryPoint->expects($this->once())
            ->method('startReAuthentication')
            ->with($this->anything(), $this->isInstanceOf(TokenInterface::class))
            ->willReturn(new Response('Confirm your password', 200));

        $listener = $this->createExceptionListener($this->createTokenStorageWithAToken(), $this->createFullFledgedTrustResolver(), null, null, null, null, $entryPoint);
        $listener->onKernelException($event);

        $this->assertSame('Confirm your password', $event->getResponse()->getContent());
    }

    public function testReAuthenticationEntryPointStartsWhenAVeryRecentAuthenticationIsRequired()
    {
        $exception = new AccessDeniedException();
        $exception->setAttributes([AuthenticatedVoter::IS_AUTHENTICATED_VERY_RECENTLY]);
        $event = $this->createEvent($exception);

        $entryPoint = $this->createMock(ReAuthenticationEntryPointInterface::class);
        $entryPoint->expects($this->once())
            ->method('startReAuthentication')
            ->willReturn(new Response('Confirm your password', 200));

        $listener = $this->createExceptionListener($this->createTokenStorageWithAToken(), $this->createFullFledgedTrustResolver(), null, null, null, null, $entryPoint);
        $listener->onKernelException($event);

        $this->assertSame('Confirm your password', $event->getResponse()->getContent());
    }

    public function testTheFirewallEntryPointIsUsedWhenItCanReAuthenticate()
    {
        $exception = new AccessDeniedException();
        $exception->setAttributes([AuthenticatedVoter::IS_AUTHENTICATED_RECENTLY]);
        $event = $this->createEvent($exception);

        // an entry point implementing both contracts needs no extra configuration; one
        // that only implements AuthenticationEntryPointInterface is never picked up,
        // which is what stops a plain login page from looping
        $entryPoint = $this->createMock(ReAuthenticatingEntryPoint::class);
        $entryPoint->expects($this->once())
            ->method('startReAuthentication')
            ->willReturn(new Response('Confirm your password', 200));

        $listener = $this->createExceptionListener($this->createTokenStorageWithAToken(), $this->createFullFledgedTrustResolver(), null, $entryPoint);
        $listener->onKernelException($event);

        $this->assertSame('Confirm your password', $event->getResponse()->getContent());
    }

    public function testAPlainFirewallEntryPointIsNotUsedToReAuthenticate()
    {
        $exception = new AccessDeniedException();
        $exception->setAttributes([AuthenticatedVoter::IS_AUTHENTICATED_RECENTLY]);
        $event = $this->createEvent($exception);

        $entryPoint = $this->createMock(AuthenticationEntryPointInterface::class);
        $entryPoint->expects($this->never())->method('start');

        $listener = $this->createExceptionListener($this->createTokenStorageWithAToken(), $this->createFullFledgedTrustResolver(), null, $entryPoint);
        $listener->onKernelException($event);

        $this->assertInstanceOf(AccessDeniedHttpException::class, $event->getThrowable());
    }

    public function testReAuthenticationEntryPointIsNotStartedForAnUnrelatedDenial()
    {
        $exception = new AccessDeniedException();
        $exception->setAttributes(['ROLE_ADMIN']);
        $event = $this->createEvent($exception);

        $entryPoint = $this->createMock(ReAuthenticationEntryPointInterface::class);
        $entryPoint->expects($this->never())->method('startReAuthentication');

        $listener = $this->createExceptionListener($this->createTokenStorageWithAToken(), $this->createFullFledgedTrustResolver(), null, null, null, null, $entryPoint);
        $listener->onKernelException($event);

        $this->assertInstanceOf(AccessDeniedHttpException::class, $event->getThrowable());
    }

    public function testReAuthenticationEntryPointIsNotStartedWhenAnotherAttributeMayHaveFailed()
    {
        // an access_control rule is decided on all of its roles at once, so this denial
        // does not say which attribute failed and re-authenticating may not help
        $exception = new AccessDeniedException();
        $exception->setAttributes(['ROLE_ADMIN', AuthenticatedVoter::IS_AUTHENTICATED_RECENTLY]);
        $event = $this->createEvent($exception);

        $entryPoint = $this->createMock(ReAuthenticationEntryPointInterface::class);
        $entryPoint->expects($this->never())->method('startReAuthentication');

        $listener = $this->createExceptionListener($this->createTokenStorageWithAToken(), $this->createFullFledgedTrustResolver(), null, null, null, null, $entryPoint);
        $listener->onKernelException($event);

        $this->assertInstanceOf(AccessDeniedHttpException::class, $event->getThrowable());
    }
}

interface ReAuthenticatingEntryPoint extends AuthenticationEntryPointInterface, ReAuthenticationEntryPointInterface
{
}
