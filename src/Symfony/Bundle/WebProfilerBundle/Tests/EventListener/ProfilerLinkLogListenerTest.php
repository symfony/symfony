<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\WebProfilerBundle\Tests\EventListener;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\WebProfilerBundle\EventListener\ProfilerLinkLogListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @author Jérémy Romey jeremyFreeAgent <jeremy@free-agent.fr>
 */
final class ProfilerLinkLogListenerTest extends TestCase
{
    public function testProfilerLinkLog()
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('debug')
            ->with('See profiler at {profiler_url}', ['profiler_url' => 'http://mydomain.com/_profiler/04bb3f'])
        ;

        (new ProfilerLinkLogListener($logger, $this->createUrlGenerator()))->onKernelResponse($this->createEvent(['X-Debug-Token' => '04bb3f']));
    }

    public function testProfilerLinkLogShouldNotLogWhenNoLogger()
    {
        (new ProfilerLinkLogListener(null, $this->createUrlGenerator(false)))->onKernelResponse($this->createEvent(['X-Debug-Token' => '04bb3f']));
    }

    public function testProfilerLinkLogShouldNotLogWhenNoUrlGenerator()
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('debug');

        (new ProfilerLinkLogListener($logger))->onKernelResponse($this->createEvent(['X-Debug-Token' => '04bb3f']));
    }

    public function testProfilerLinkLogShouldNotLogWhenNotMainRequest()
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('debug');

        (new ProfilerLinkLogListener($logger, $this->createUrlGenerator(false)))->onKernelResponse($this->createEvent(['X-Debug-Token' => '04bb3f'], HttpKernelInterface::SUB_REQUEST));
    }

    public function testProfilerLinkLogShouldNotLogWhenNoToken()
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('debug');

        (new ProfilerLinkLogListener($logger, $this->createUrlGenerator(false)))->onKernelResponse($this->createEvent([]));
    }

    private function createUrlGenerator(bool $expectCall = true): UrlGeneratorInterface
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects($expectCall ? $this->once() : $this->never())
            ->method('generate')
            ->with('_profiler', ['token' => '04bb3f'], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('http://mydomain.com/_profiler/04bb3f')
        ;

        return $urlGenerator;
    }

    private function createEvent(array $headers, int $requestType = HttpKernelInterface::MAIN_REQUEST): ResponseEvent
    {
        $response = new Response('I love Symfony', 200);
        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value);
        }

        return new ResponseEvent($this->createStub(Kernel::class), new Request(), $requestType, $response);
    }
}
