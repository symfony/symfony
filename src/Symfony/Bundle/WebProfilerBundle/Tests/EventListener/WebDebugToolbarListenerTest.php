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
use Symfony\Bundle\WebProfilerBundle\EventListener\WebDebugToolbarListener;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollectorInterface;
use Symfony\Component\HttpKernel\DataCollector\DumpDataCollector;
use Symfony\Component\HttpKernel\DataCollector\ExceptionDataCollector;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Profiler\Profile;
use Symfony\Component\HttpKernel\Profiler\ProfileStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class WebDebugToolbarListenerTest extends TestCase
{
    /**
     * @dataProvider getInjectToolbarTests
     */
    public function testInjectToolbar($content, $expected)
    {
        $listener = new WebDebugToolbarListener($this->getTwigMock());
        $m = new \ReflectionMethod($listener, 'injectToolbar');
        $m->setAccessible(true);

        $response = new Response($content);

        $m->invoke($listener, $response, Request::create('/'), ['csp_script_nonce' => 'scripto', 'csp_style_nonce' => 'stylo']);
        $this->assertEquals($expected, $response->getContent());
    }

    public function getInjectToolbarTests()
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
    public function testHtmlRedirectionIsIntercepted($statusCode, $hasSession)
    {
        $response = new Response('Some content', $statusCode);
        $response->headers->set('X-Debug-Token', 'xxxxxxxx');
        $event = new ResponseEvent($this->getKernelMock(), $this->getRequestMock(false, 'html', $hasSession), HttpKernelInterface::MASTER_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock('Redirection'), true);
        $listener->onKernelResponse($event);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Redirection', $response->getContent());
    }

    public function testNonHtmlRedirectionIsNotIntercepted()
    {
        $response = new Response('Some content', '301');
        $response->headers->set('X-Debug-Token', 'xxxxxxxx');
        $event = new ResponseEvent($this->getKernelMock(), $this->getRequestMock(false, 'json', true), HttpKernelInterface::MASTER_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock('Redirection'), true);
        $listener->onKernelResponse($event);

        $this->assertEquals(301, $response->getStatusCode());
        $this->assertEquals('Some content', $response->getContent());
    }

    public function testToolbarIsInjected()
    {
        $response = new Response('<html><head></head><body></body></html>');
        $response->headers->set('X-Debug-Token', 'xxxxxxxx');

        $event = new ResponseEvent($this->getKernelMock(), $this->getRequestMock(), HttpKernelInterface::MASTER_REQUEST, $response);

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
        $event = new ResponseEvent($this->getKernelMock(), $this->getRequestMock(), HttpKernelInterface::MASTER_REQUEST, $response);

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
        $event = new ResponseEvent($this->getKernelMock(), $this->getRequestMock(false, 'html'), HttpKernelInterface::MASTER_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock());
        $listener->onKernelResponse($event);

        $this->assertEquals('<html><head></head><body></body></html>', $response->getContent());
    }

    /**
     * @depends testToolbarIsInjected
     * @dataProvider provideRedirects
     */
    public function testToolbarIsNotInjectedOnRedirection($statusCode, $hasSession)
    {
        $response = new Response('<html><head></head><body></body></html>', $statusCode);
        $response->headers->set('X-Debug-Token', 'xxxxxxxx');
        $event = new ResponseEvent($this->getKernelMock(), $this->getRequestMock(false, 'html', $hasSession), HttpKernelInterface::MASTER_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock());
        $listener->onKernelResponse($event);

        $this->assertEquals('<html><head></head><body></body></html>', $response->getContent());
    }

    public function provideRedirects()
    {
        return [
            [301, true],
            [302, true],
            [301, false],
            [302, false],
        ];
    }

    /**
     * @depends testToolbarIsInjected
     */
    public function testToolbarIsNotInjectedWhenThereIsNoNoXDebugTokenResponseHeader()
    {
        $response = new Response('<html><head></head><body></body></html>');

        $event = new ResponseEvent($this->getKernelMock(), $this->getRequestMock(), HttpKernelInterface::MASTER_REQUEST, $response);

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

        $event = new ResponseEvent($this->getKernelMock(), $this->getRequestMock(), HttpKernelInterface::SUB_REQUEST, $response);

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

        $event = new ResponseEvent($this->getKernelMock(), $this->getRequestMock(), HttpKernelInterface::MASTER_REQUEST, $response);

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

        $event = new ResponseEvent($this->getKernelMock(), $this->getRequestMock(true), HttpKernelInterface::MASTER_REQUEST, $response);

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

        $event = new ResponseEvent($this->getKernelMock(), $this->getRequestMock(false, 'json'), HttpKernelInterface::MASTER_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock());
        $listener->onKernelResponse($event);

        $this->assertEquals('<html><head></head><body></body></html>', $response->getContent());
    }

    public function testXDebugUrlHeader()
    {
        $response = new Response();
        $response->headers->set('X-Debug-Token', 'xxxxxxxx');

        $urlGenerator = $this->getUrlGeneratorMock();
        $urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('_profiler', ['token' => 'xxxxxxxx', 'panel' => null], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn('http://mydomain.com/_profiler/xxxxxxxx')
        ;

        $event = new ResponseEvent($this->getKernelMock(), $this->getRequestMock(), HttpKernelInterface::MASTER_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock(), false, WebDebugToolbarListener::ENABLED, $urlGenerator);
        $listener->onKernelResponse($event);

        $this->assertEquals('http://mydomain.com/_profiler/xxxxxxxx', $response->headers->get('X-Debug-Token-Link'));
    }

    public function testThrowingUrlGenerator()
    {
        $response = new Response();
        $response->headers->set('X-Debug-Token', 'xxxxxxxx');

        $urlGenerator = $this->getUrlGeneratorMock();
        $urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('_profiler', ['token' => 'xxxxxxxx', 'panel' => null])
            ->willThrowException(new \Exception('foo'))
        ;

        $event = new ResponseEvent($this->getKernelMock(), $this->getRequestMock(), HttpKernelInterface::MASTER_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock(), false, WebDebugToolbarListener::ENABLED, $urlGenerator);
        $listener->onKernelResponse($event);

        $this->assertEquals('Exception: foo', $response->headers->get('X-Debug-Error'));
    }

    public function testThrowingErrorCleanup()
    {
        $response = new Response();
        $response->headers->set('X-Debug-Token', 'xxxxxxxx');

        $urlGenerator = $this->getUrlGeneratorMock();
        $urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('_profiler', ['token' => 'xxxxxxxx', 'panel' => null])
            ->willThrowException(new \Exception("This\nmultiline\r\ntabbed text should\tcome out\r on\n \ta single plain\r\nline"))
        ;

        $event = new ResponseEvent($this->getKernelMock(), $this->getRequestMock(), HttpKernelInterface::MASTER_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock(), false, WebDebugToolbarListener::ENABLED, $urlGenerator);
        $listener->onKernelResponse($event);

        $this->assertEquals('Exception: This multiline tabbed text should come out on a single plain line', $response->headers->get('X-Debug-Error'));
    }

    public function testToolbarIsInjectedWithProfileStack()
    {
        $response = new Response('<html><head></head><body></body></html>');

        $event = new ResponseEvent($this->getKernelMock(), $request = $this->getRequestMock(), HttpKernelInterface::MASTER_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock(), false, WebDebugToolbarListener::ENABLED, null, '', null, $profileStack = new ProfileStack());

        $profileStack->set($request, new Profile('foobar'));

        $listener->onKernelResponse($event);

        $this->assertEquals("<html><head></head><body>\nWDT\n</body></html>", $response->getContent());
    }

    /**
     * @dataProvider linksToPanelsProvider
     */
    public function testLinksToPanels(DataCollectorInterface $dataCollector, Request $request, Response $response)
    {
        $urlGenerator = $this->getUrlGeneratorMock();
        $urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('_profiler', ['token' => $token = 'xxxxxx', 'panel' => $dataCollector->getName()], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn($expectedLink = 'http://mydomain.com/_profiler/'.$dataCollector->getName());

        $event = new ResponseEvent($this->getKernelMock(), $request, HttpKernelInterface::MASTER_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock(), false, WebDebugToolbarListener::ENABLED, $urlGenerator, '', null, $profileStack = new ProfileStack());

        $profileStack->set($request, $profile = new Profile($token));
        $profile->addCollector($dataCollector);
        $profile->addCollector($this->createMock(DataCollectorInterface::class));

        $listener->onKernelResponse($event);

        $this->assertEquals($expectedLink, $response->headers->get('X-Debug-Token-Link'));
    }

    public function linksToPanelsProvider()
    {
        $exceptionDataCollector = new ExceptionDataCollector();
        $exceptionDataCollector->collect($request = new Request(), $response = new Response(), new \DomainException());

        yield [$exceptionDataCollector, $request, $response];

        $dumpDataCollector = $this->createMock(DumpDataCollector::class);
        $dumpDataCollector
            ->expects($this->atLeastOnce())
            ->method('getName')
            ->willReturn('dump');
        $dumpDataCollector
            ->expects($this->atLeastOnce())
            ->method('getDumpsCount')
            ->willReturn(1);

        yield [$dumpDataCollector, new Request(), new Response()];
    }

    public function testLinkToExceptionPanelPriority()
    {
        $exceptionDataCollector = new ExceptionDataCollector();
        $exceptionDataCollector->collect($request = new Request(), $response = new Response(), new \DomainException());

        $urlGenerator = $this->getUrlGeneratorMock();
        $urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('_profiler', ['token' => $token = 'xxxxxx', 'panel' => $exceptionDataCollector->getName()], UrlGeneratorInterface::ABSOLUTE_URL)
            ->willReturn($expectedLink = 'http://mydomain.com/_profiler/'.$exceptionDataCollector->getName());

        $event = new ResponseEvent($this->getKernelMock(), $request, HttpKernelInterface::MASTER_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock(), false, WebDebugToolbarListener::ENABLED, $urlGenerator, '', null, $profileStack = new ProfileStack());

        $profileStack->set($request, $profile = new Profile($token));
        $profile->addCollector($exceptionDataCollector);

        $dumpDataCollector = $this->createMock(DumpDataCollector::class);
        $dumpDataCollector
            ->expects($this->never())
            ->method('getDumpsCount');

        $profile->addCollector($dumpDataCollector);

        $listener->onKernelResponse($event);

        $this->assertEquals($expectedLink, $response->headers->get('X-Debug-Token-Link'));
    }

    protected function getRequestMock($isXmlHttpRequest = false, $requestFormat = 'html', $hasSession = true)
    {
        $request = $this->getMockBuilder('Symfony\Component\HttpFoundation\Request')->setMethods(['getSession', 'isXmlHttpRequest', 'getRequestFormat'])->disableOriginalConstructor()->getMock();
        $request->expects($this->any())
            ->method('isXmlHttpRequest')
            ->willReturn($isXmlHttpRequest);
        $request->expects($this->any())
            ->method('getRequestFormat')
            ->willReturn($requestFormat);

        $request->headers = new HeaderBag();

        if ($hasSession) {
            $session = $this->getMockBuilder('Symfony\Component\HttpFoundation\Session\Session')->disableOriginalConstructor()->getMock();
            $request->expects($this->any())
                ->method('getSession')
                ->willReturn($session);
        }

        return $request;
    }

    protected function getTwigMock($render = 'WDT')
    {
        $templating = $this->getMockBuilder('Twig\Environment')->disableOriginalConstructor()->getMock();
        $templating->expects($this->any())
            ->method('render')
            ->willReturn($render);

        return $templating;
    }

    protected function getUrlGeneratorMock()
    {
        return $this->getMockBuilder('Symfony\Component\Routing\Generator\UrlGeneratorInterface')->getMock();
    }

    protected function getKernelMock()
    {
        return $this->getMockBuilder('Symfony\Component\HttpKernel\Kernel')->disableOriginalConstructor()->getMock();
    }
}
