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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\WebProfilerBundle\EventListener\WebProfilerChromeDevToolsListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\Profiler\Profiler;

class WebProfilerChromeDevToolsListenerTest extends TestCase
{
    public function testDisablesProfilerOnDevToolsPath()
    {
        $profiler = $this->createMock(Profiler::class);
        $profiler->expects($this->once())->method('disable');

        $listener = new WebProfilerChromeDevToolsListener($profiler);
        $listener->onKernelRequest($this->createEvent('/.well-known/appspecific/com.chrome.devtools.json'));
    }

    public function testDisablesProfilerWithQueryString()
    {
        $profiler = $this->createMock(Profiler::class);
        $profiler->expects($this->once())->method('disable');

        $listener = new WebProfilerChromeDevToolsListener($profiler);
        $listener->onKernelRequest($this->createEvent('/.well-known/appspecific/com.chrome.devtools.json?foo=bar'));
    }

    #[DataProvider('provideNonMatchingPaths')]
    public function testDoesNothingOnOtherPaths(string $path)
    {
        $profiler = $this->createMock(Profiler::class);
        $profiler->expects($this->never())->method('disable');

        $listener = new WebProfilerChromeDevToolsListener($profiler);
        $listener->onKernelRequest($this->createEvent($path));
    }

    public static function provideNonMatchingPaths(): iterable
    {
        yield 'unrelated path' => ['/foo'];
        yield 'sibling well-known' => ['/.well-known/appspecific/other.json'];
        yield 'trailing slash' => ['/.well-known/appspecific/com.chrome.devtools.json/'];
        yield 'suffix extension' => ['/.well-known/appspecific/com.chrome.devtools.json.bak'];
        yield 'percent-encoded dot' => ['/.well-known/appspecific/com.chrome.devtools%2Ejson'];
    }

    public function testIgnoresSubRequests()
    {
        $profiler = $this->createMock(Profiler::class);
        $profiler->expects($this->never())->method('disable');

        $listener = new WebProfilerChromeDevToolsListener($profiler);
        $listener->onKernelRequest($this->createEvent('/.well-known/appspecific/com.chrome.devtools.json', HttpKernelInterface::SUB_REQUEST));
    }

    public function testWorksWithoutProfiler()
    {
        $this->expectNotToPerformAssertions();

        $listener = new WebProfilerChromeDevToolsListener(null);
        $listener->onKernelRequest($this->createEvent('/.well-known/appspecific/com.chrome.devtools.json'));
        $listener->onKernelRequest($this->createEvent('/foo'));
    }

    private function createEvent(string $pathWithQuery, int $type = HttpKernelInterface::MAIN_REQUEST): RequestEvent
    {
        return new RequestEvent(
            $this->createStub(KernelInterface::class),
            Request::create($pathWithQuery),
            $type,
        );
    }
}
