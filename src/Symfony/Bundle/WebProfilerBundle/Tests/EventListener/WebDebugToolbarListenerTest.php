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
use Symfony\Bundle\WebProfilerBundle\Csp\ContentSecurityPolicyHandler;
use Symfony\Bundle\WebProfilerBundle\EventListener\WebDebugToolbarListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DumpDataCollector;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class WebDebugToolbarListenerTest extends TestCase
{
    /**
     * @dataProvider getInjectToolbarTests
     */
    public function testInjectToolbar($content, $expected)
    {
        $listener = new WebDebugToolbarListener($this->getTwigMock());
        $m = new \ReflectionMethod($listener, 'injectToolbar');

        $response = new Response($content);

        $m->invoke($listener, $response, Request::create('/'), ['csp_script_nonce' => 'scripto', 'csp_style_nonce' => 'stylo']);
        $this->assertEquals($expected, $response->getContent());
    }

    public static function getInjectToolbarTests()
    {
        return [
            ['<html><head></head><body></body></html>', "<html><head></head><body>\nWDT\n</body></html>"],
            ['<html>
            <head></head>
            <body>
            <textarea><html><head></head><body></body></html></textarea>
            </body>
            </html>', "<html>
            <head></head>
            <body>
            <textarea><html><head></head><body></body></html></textarea>
            \nWDT\n</body>
            </html>"],
        ];
    }

    /**
     * @dataProvider provideRedirects
     */
    public function testHtmlRedirectionIsIntercepted($statusCode)
    {
        $response = new Response('Some content', $statusCode);
        $response->headers->set('X-Debug-Token', 'xxxxxxxx');
        $event = new ResponseEvent($this->createMock(Kernel::class), new Request(), HttpKernelInterface::MAIN_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock('Redirection'), true);
        $listener->onKernelResponse($event);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Redirection', $response->getContent());
    }

    public function testNonHtmlRedirectionIsNotIntercepted()
    {
        $response = new Response('Some content', '301');
        $response->headers->set('X-Debug-Token', 'xxxxxxxx');
        $event = new ResponseEvent($this->createMock(Kernel::class), new Request([], [], ['_format' => 'json']), HttpKernelInterface::MAIN_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock('Redirection'), true);
        $listener->onKernelResponse($event);

        $this->assertEquals(301, $response->getStatusCode());
        $this->assertEquals('Some content', $response->getContent());
    }

    public function testToolbarIsInjected()
    {
        $response = new Response('<html><head></head><body></body></html>');
        $response->headers->set('X-Debug-Token', 'xxxxxxxx');

        $event = new ResponseEvent($this->createMock(Kernel::class), new Request(), HttpKernelInterface::MAIN_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock());
        $listener->onKernelResponse($event);

        $this->assertEquals("<html><head></head><body>\nWDT\n</body></html>", $response->getContent());
    }

    /**
     * @depends testToolbarIsInjected
     */
    public function testToolbarIsNotInjectedOnNonHtmlContentType()
    {
        $response = new Response('<html><head></head><body></body></html>');
        $response->headers->set('X-Debug-Token', 'xxxxxxxx');
        $response->headers->set('Content-Type', 'text/xml');
        $event = new ResponseEvent($this->createMock(Kernel::class), new Request(), HttpKernelInterface::MAIN_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock());
        $listener->onKernelResponse($event);

        $this->assertEquals('<html><head></head><body></body></html>', $response->getContent());
    }

    /**
     * @depends testToolbarIsInjected
     */
    public function testToolbarIsNotInjectedOnContentDispositionAttachment()
    {
        $response = new Response('<html><head></head><body></body></html>');
        $response->headers->set('X-Debug-Token', 'xxxxxxxx');
        $response->headers->set('Content-Disposition', 'attachment; filename=test.html');
        $event = new ResponseEvent($this->createMock(Kernel::class), new Request(), HttpKernelInterface::MAIN_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock());
        $listener->onKernelResponse($event);

        $this->assertEquals('<html><head></head><body></body></html>', $response->getContent());
    }

    /**
     * @depends testToolbarIsInjected
     *
     * @dataProvider provideRedirects
     */
    public function testToolbarIsNotInjectedOnRedirection($statusCode)
    {
        $response = new Response('<html><head></head><body></body></html>', $statusCode);
        $response->headers->set('X-Debug-Token', 'xxxxxxxx');
        $event = new ResponseEvent($this->createMock(Kernel::class), new Request(), HttpKernelInterface::MAIN_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock());
        $listener->onKernelResponse($event);

        $this->assertEquals('<html><head></head><body></body></html>', $response->getContent());
    }

    public static function provideRedirects()
    {
        return [
            [301],
            [302],
        ];
    }

    /**
     * @depends testToolbarIsInjected
     */
    public function testToolbarIsNotInjectedWhenThereIsNoNoXDebugTokenResponseHeader()
    {
        $response = new Response('<html><head></head><body></body></html>');

        $event = new ResponseEvent($this->createMock(Kernel::class), new Request(), HttpKernelInterface::MAIN_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock());
        $listener->onKernelResponse($event);

        $this->assertEquals('<html><head></head><body></body></html>', $response->getContent());
    }

    /**
     * @depends testToolbarIsInjected
     */
    public function testToolbarIsNotInjectedWhenOnSubRequest()
    {
        $response = new Response('<html><head></head><body></body></html>');
        $response->headers->set('X-Debug-Token', 'xxxxxxxx');

        $event = new ResponseEvent($this->createMock(Kernel::class), new Request(), HttpKernelInterface::SUB_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock());
        $listener->onKernelResponse($event);

        $this->assertEquals('<html><head></head><body></body></html>', $response->getContent());
    }

    /**
     * @depends testToolbarIsInjected
     */
    public function testToolbarIsNotInjectedOnIncompleteHtmlResponses()
    {
        $response = new Response('<div>Some content</div>');
        $response->headers->set('X-Debug-Token', 'xxxxxxxx');

        $event = new ResponseEvent($this->createMock(Kernel::class), new Request(), HttpKernelInterface::MAIN_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock());
        $listener->onKernelResponse($event);

        $this->assertEquals('<div>Some content</div>', $response->getContent());
    }

    /**
     * @depends testToolbarIsInjected
     */
    public function testToolbarIsNotInjectedOnXmlHttpRequests()
    {
        $response = new Response('<html><head></head><body></body></html>');
        $response->headers->set('X-Debug-Token', 'xxxxxxxx');

        $request = new Request();
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        $event = new ResponseEvent($this->createMock(Kernel::class), $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock());
        $listener->onKernelResponse($event);

        $this->assertEquals('<html><head></head><body></body></html>', $response->getContent());
    }

    /**
     * @depends testToolbarIsInjected
     */
    public function testToolbarIsNotInjectedOnNonHtmlRequests()
    {
        $response = new Response('<html><head></head><body></body></html>');
        $response->headers->set('X-Debug-Token', 'xxxxxxxx');

        $event = new ResponseEvent($this->createMock(Kernel::class), new Request([], [], ['_format' => 'json']), HttpKernelInterface::MAIN_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock());
        $listener->onKernelResponse($event);

        $this->assertEquals('<html><head></head><body></body></html>', $response->getContent());
    }

    public function testXDebugUrlHeader()
    {
        $response = new Response();
        $response->headers->set('X-Debug-Token', 'xxxxxxxx');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('_profiler', ['token' => 'xxxxxxxx'], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('http://mydomain.com/_profiler/xxxxxxxx')
        ;

        $event = new ResponseEvent($this->createMock(Kernel::class), new Request(), HttpKernelInterface::MAIN_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock(), false, WebDebugToolbarListener::ENABLED, $urlGenerator);
        $listener->onKernelResponse($event);

        $this->assertEquals('http://mydomain.com/_profiler/xxxxxxxx', $response->headers->get('X-Debug-Token-Link'));
    }

    public function testThrowingUrlGenerator()
    {
        $response = new Response();
        $response->headers->set('X-Debug-Token', 'xxxxxxxx');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('_profiler', ['token' => 'xxxxxxxx'])
            ->willThrowException(new \Exception('foo'))
        ;

        $event = new ResponseEvent($this->createMock(Kernel::class), new Request(), HttpKernelInterface::MAIN_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock(), false, WebDebugToolbarListener::ENABLED, $urlGenerator);
        $listener->onKernelResponse($event);

        $this->assertEquals('Exception: foo', $response->headers->get('X-Debug-Error'));
    }

    public function testThrowingErrorCleanup()
    {
        $response = new Response();
        $response->headers->set('X-Debug-Token', 'xxxxxxxx');

        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('_profiler', ['token' => 'xxxxxxxx'])
            ->willThrowException(new \Exception("This\nmultiline\r\ntabbed text should\tcome out\r on\n \ta single plain\r\nline"))
        ;

        $event = new ResponseEvent($this->createMock(Kernel::class), new Request(), HttpKernelInterface::MAIN_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock(), false, WebDebugToolbarListener::ENABLED, $urlGenerator);
        $listener->onKernelResponse($event);

        $this->assertEquals('Exception: This multiline tabbed text should come out on a single plain line', $response->headers->get('X-Debug-Error'));
    }

    public function testCspIsDisabledIfDumperWasUsed()
    {
        $response = new Response('<html><head></head><body></body></html>');
        $response->headers->set('X-Debug-Token', 'xxxxxxxx');

        $event = new ResponseEvent($this->createMock(Kernel::class), new Request(), HttpKernelInterface::MAIN_REQUEST, $response);

        $cspHandler = $this->createMock(ContentSecurityPolicyHandler::class);
        $cspHandler->expects($this->once())
            ->method('disableCsp');
        $dumpDataCollector = $this->createMock(DumpDataCollector::class);
        $dumpDataCollector->expects($this->once())
            ->method('getDumpsCount')
            ->willReturn(1);

        $listener = new WebDebugToolbarListener($this->getTwigMock(), false, WebDebugToolbarListener::ENABLED, null, '', $cspHandler, $dumpDataCollector);
        $listener->onKernelResponse($event);

        $this->assertEquals("<html><head></head><body>\nWDT\n</body></html>", $response->getContent());
    }

    public function testCspIsKeptEnabledIfDumperWasNotUsed()
    {
        $response = new Response('<html><head></head><body></body></html>');
        $response->headers->set('X-Debug-Token', 'xxxxxxxx');

        $event = new ResponseEvent($this->createMock(Kernel::class), new Request(), HttpKernelInterface::MAIN_REQUEST, $response);

        $cspHandler = $this->createMock(ContentSecurityPolicyHandler::class);
        $cspHandler->expects($this->never())
            ->method('disableCsp');
        $dumpDataCollector = $this->createMock(DumpDataCollector::class);
        $dumpDataCollector->expects($this->once())
            ->method('getDumpsCount')
            ->willReturn(0);

        $listener = new WebDebugToolbarListener($this->getTwigMock(), false, WebDebugToolbarListener::ENABLED, null, '', $cspHandler, $dumpDataCollector);
        $listener->onKernelResponse($event);

        $this->assertEquals("<html><head></head><body>\nWDT\n</body></html>", $response->getContent());
    }

    protected function getTwigMock($render = 'WDT')
    {
        $templating = $this->createMock(Environment::class);
        $templating->expects($this->any())
            ->method('render')
            ->willReturn($render);

        return $templating;
    }
}
