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

        $kernel = $this->createMock(HttpKernelInterface::class);
        $mainRequest = $this->createMock(Request::class);
        $subRequest = $this->createMock(Request::class);
        $response = $this->createMock(Response::class);

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

    /**
     * @dataProvider collectRequestProvider
     */
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

        $kernel = $this->createMock(HttpKernelInterface::class);
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
}
