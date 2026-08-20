<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\EventListener;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\EventListener\ProfilerListener;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\HttpKernel\Profiler\Profiler;

class ProfilerListenerTest extends TestCase
{
    /**
     * Test a main and sub request with an exception and `onlyException` profiler option enabled.
     */
    public function testKernelTerminate()
    {
        $profile = new Profile('token');

        $profiler = $this->createMock(Profiler::class);
        $profiler->expects($this->once())
            ->method('collect')
            ->willReturn($profile);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $mainRequest = new Request();
        $subRequest = new Request();
        $response = new Response();

        $requestStack = new RequestStack();
        $requestStack->push($mainRequest);

        $onlyException = true;
        $listener = new ProfilerListener($profiler, $requestStack, null, $onlyException);

        // main request
        $listener->onKernelResponse(new ResponseEvent($kernel, $mainRequest, Kernel::MAIN_REQUEST, $response));

        // sub request
        $listener->onKernelException(new ExceptionEvent($kernel, $subRequest, Kernel::SUB_REQUEST, new HttpException(404)));
        $listener->onKernelResponse(new ResponseEvent($kernel, $subRequest, Kernel::SUB_REQUEST, $response));

        $listener->onKernelTerminate(new TerminateEvent($kernel, $mainRequest, $response));
    }

    #[DataProvider('collectRequestProvider')]
    public function testCollectParameter(Request $request, ?bool $enable)
    {
        $profile = new Profile('token');

        $profiler = $this->createMock(Profiler::class);
        $profiler->expects($this->once())
            ->method('collect')
            ->willReturn($profile);

        $profiler
            ->expects(null === $enable ? $this->never() : $this->once())
            ->method($enable ? 'enable' : 'disable');

        $kernel = $this->createStub(HttpKernelInterface::class);
        $response = new Response();

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $listener = new ProfilerListener($profiler, $requestStack, null, false, false, 'profile');

        $listener->onKernelResponse(new ResponseEvent($kernel, $request, Kernel::MAIN_REQUEST, $response));
    }

    public static function collectRequestProvider(): iterable
    {
        yield [Request::create('/'), null];
        yield [Request::create('/', 'GET', ['profile' => '1']), true];
        yield [Request::create('/', 'GET', ['profile' => '0']), false];

        $request = Request::create('/');
        $request->attributes->set('profile', true);
        yield [$request, true];
    }

    #[DataProvider('excludedPathsProvider')]
    public function testExcludedPaths(string $pathInfo, array $excludedPaths, bool $collected)
    {
        $this->assertProfileCollected($collected, Request::create($pathInfo), new Response(), $excludedPaths);
    }

    public static function excludedPathsProvider(): iterable
    {
        yield 'no exclusion configured' => ['/', [], true];
        yield 'the Chrome DevTools probe' => ['/.well-known/appspecific/com.chrome.devtools.json', ['^/\.well-known/'], false];
        yield 'non-matching path' => ['/api/foo', ['^/\.well-known/'], true];
        yield 'patterns are case-sensitive' => ['/.WELL-KNOWN/x', ['^/\.well-known/'], true];
        yield 'patterns are alternated' => ['/bar', ['^/foo', '^/bar'], false];
        yield 'patterns are not anchored' => ['/a/.well-known/b', ['\.well-known'], false];
        yield 'the path info is url-decoded' => ['/étape', ['^/étape'], false];
    }

    #[DataProvider('excludedHttpCodesProvider')]
    public function testExcludedHttpCodes(int $statusCode, string $pathInfo, array $excludedHttpCodes, bool $collected)
    {
        $this->assertProfileCollected($collected, Request::create($pathInfo), new Response('', $statusCode), [], $excludedHttpCodes);
    }

    public static function excludedHttpCodesProvider(): iterable
    {
        yield 'no exclusion configured' => [200, '/', [], true];
        yield 'matching code' => [404, '/', [404 => []], false];
        yield 'non-matching code' => [200, '/', [404 => []], true];
        yield 'other error code' => [500, '/', [404 => []], true];
        yield 'matching code, non-matching path' => [404, '/foo', [404 => ['^/bar']], true];
        yield 'matching code and path' => [404, '/bar', [404 => ['^/bar']], false];
        yield 'paths are case-sensitive' => [404, '/FOO', [404 => ['^/foo']], true];
        yield 'the second code matches' => [400, '/foo', [404 => [], 400 => ['^/foo']], false];
    }

    #[DataProvider('exclusionsOfTheDevToolsProbeProvider')]
    public function testExcludedRequestDoesNotLeakItsExceptionToTheNextRequest(array $excludedPaths, array $excludedHttpCodes)
    {
        $profiler = $this->createMock(Profiler::class);
        $profiler->expects($this->once())
            ->method('collect')
            ->with($this->anything(), $this->anything(), $this->isNull())
            ->willReturn(new Profile('token'));

        $kernel = $this->createStub(HttpKernelInterface::class);

        $excluded = Request::create('/.well-known/appspecific/com.chrome.devtools.json');
        $next = Request::create('/');

        $requestStack = new RequestStack();
        $requestStack->push($excluded);

        $listener = new ProfilerListener($profiler, $requestStack, null, false, false, null, $excludedPaths, $excludedHttpCodes);

        $listener->onKernelException(new ExceptionEvent($kernel, $excluded, Kernel::MAIN_REQUEST, new HttpException(404)));
        $listener->onKernelResponse(new ResponseEvent($kernel, $excluded, Kernel::MAIN_REQUEST, new Response('', 404)));

        $requestStack->pop();
        $requestStack->push($next);
        $listener->onKernelResponse(new ResponseEvent($kernel, $next, Kernel::MAIN_REQUEST, new Response()));
    }

    public static function exclusionsOfTheDevToolsProbeProvider(): iterable
    {
        yield 'by path' => [['^/\.well-known/'], []];
        yield 'by HTTP status code' => [[], [404 => []]];
    }

    #[DataProvider('collectParameterValueProvider')]
    public function testExcludedRequestIsNotForcedByTheCollectParameter(string $collectParameterValue)
    {
        $profiler = $this->createMock(Profiler::class);
        $profiler->expects($this->never())->method('collect');
        $profiler->expects($this->never())->method('enable');
        $profiler->expects($this->never())->method('disable');

        $request = Request::create('/.well-known/appspecific/com.chrome.devtools.json', 'GET', ['profile' => $collectParameterValue]);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $listener = new ProfilerListener($profiler, $requestStack, null, false, false, 'profile', ['^/\.well-known/']);
        $listener->onKernelResponse(new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, Kernel::MAIN_REQUEST, new Response()));
    }

    public static function collectParameterValueProvider(): iterable
    {
        yield 'the parameter cannot enable collection' => ['1'];
        yield 'the parameter cannot disable the profiler for the next requests' => ['0'];
    }

    public function testSubRequestsAreExcludedOnTheirOwnPathInfo()
    {
        $profiler = $this->createMock(Profiler::class);
        $profiler->expects($this->once())
            ->method('collect')
            ->with($this->callback(static fn (Request $request) => '/' === $request->getPathInfo()))
            ->willReturn(new Profile('token'));

        $kernel = $this->createStub(HttpKernelInterface::class);
        $response = new Response();

        $mainRequest = Request::create('/');
        $subRequest = Request::create('/.well-known/appspecific/com.chrome.devtools.json');

        $requestStack = new RequestStack();
        $requestStack->push($mainRequest);

        $listener = new ProfilerListener($profiler, $requestStack, null, false, false, null, ['^/\.well-known/']);

        $requestStack->push($subRequest);
        $listener->onKernelResponse(new ResponseEvent($kernel, $subRequest, Kernel::SUB_REQUEST, $response));
        $requestStack->pop();

        $listener->onKernelResponse(new ResponseEvent($kernel, $mainRequest, Kernel::MAIN_REQUEST, $response));
        $listener->onKernelTerminate(new TerminateEvent($kernel, $mainRequest, $response));
    }

    public function testExcludingTheMainRequestKeepsTheProfilesOfItsSubRequests()
    {
        $profile = new Profile('token');

        $profiler = $this->createMock(Profiler::class);
        $profiler->expects($this->once())
            ->method('collect')
            ->with($this->callback(static fn (Request $request) => '/inner' === $request->getPathInfo()))
            ->willReturn($profile);
        $profiler->expects($this->once())
            ->method('saveProfile')
            ->with($profile);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $response = new Response();

        $mainRequest = Request::create('/.well-known/appspecific/com.chrome.devtools.json');
        $subRequest = Request::create('/inner');

        $requestStack = new RequestStack();
        $requestStack->push($mainRequest);

        $listener = new ProfilerListener($profiler, $requestStack, null, false, false, null, ['^/\.well-known/']);

        $requestStack->push($subRequest);
        $listener->onKernelResponse(new ResponseEvent($kernel, $subRequest, Kernel::SUB_REQUEST, $response));
        $requestStack->pop();

        $listener->onKernelResponse(new ResponseEvent($kernel, $mainRequest, Kernel::MAIN_REQUEST, $response));
        $listener->onKernelTerminate(new TerminateEvent($kernel, $mainRequest, $response));
    }

    public function testExcludedPathsRejectInvalidRegularExpressions()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(\sprintf('Invalid regular expression in the "$excludedPaths" argument of "%s": "^/foo(".', ProfilerListener::class));

        new ProfilerListener($this->createStub(Profiler::class), new RequestStack(), null, false, false, null, ['^/foo(']);
    }

    public function testExcludedHttpCodesRejectInvalidRegularExpressions()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage(\sprintf('Invalid regular expression in the "$excludedHttpCodes" argument of "%s": "^/foo(".', ProfilerListener::class));

        new ProfilerListener($this->createStub(Profiler::class), new RequestStack(), null, false, false, null, [], [404 => ['^/foo(']]);
    }

    private function assertProfileCollected(bool $expected, Request $request, Response $response, array $excludedPaths = [], array $excludedHttpCodes = []): void
    {
        $profiler = $this->createMock(Profiler::class);
        $profiler->expects($expected ? $this->once() : $this->never())
            ->method('collect')
            ->willReturn(new Profile('token'));

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $listener = new ProfilerListener($profiler, $requestStack, null, false, false, null, $excludedPaths, $excludedHttpCodes);
        $listener->onKernelResponse(new ResponseEvent($this->createStub(HttpKernelInterface::class), $request, Kernel::MAIN_REQUEST, $response));
    }
}
