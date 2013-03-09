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

use Symfony\Bundle\WebProfilerBundle\EventListener\WebDebugToolbarListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class WebDebugToolbarListenerTest extends \PHPUnit_Framework_TestCase
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

        $m->invoke($listener, $response);
        $this->assertEquals($expected, $response->getContent());
    }

    public function getInjectToolbarTests()
    {
        return array(
            array('<html><head></head><body></body></html>', "<html><head></head><body>\nWDT\n</body></html>"),
            array('<html>
            <head></head>
            <body>
            <textarea><html><head></head><body></body></html></textarea>
            </body>
            </html>', "<html>
            <head></head>
            <body>
            <textarea><html><head></head><body></body></html></textarea>
            \nWDT\n</body>
            </html>"),
        );
    }

    /**
     * @dataProvider provideRedirects
     */
    public function testRedirectionIsIntercepted($statusCode, $hasSession)
    {
        $response = new Response('Some content', $statusCode);
        $response->headers->set('X-Debug-Token', 'xxxxxxxx');
        $event = new FilterResponseEvent($this->getKernelMock(), $this->getRequestMock(false, 'html', $hasSession), HttpKernelInterface::MASTER_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock('Redirection'), true);
        $listener->onKernelResponse($event);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Redirection', $response->getContent());
    }

    public function testToolbarIsInjected()
    {
        $response = new Response('<html><head></head><body></body></html>');
        $response->headers->set('X-Debug-Token', 'xxxxxxxx');

        $event = new FilterResponseEvent($this->getKernelMock(), $this->getRequestMock(), HttpKernelInterface::MASTER_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock());
        $listener->onKernelResponse($event);

        $this->assertEquals("<html><head></head><body>\nWDT\n</body></html>", $response->getContent());
    }

    /**
     * @depends testToolbarIsInjected
     * @dataProvider provideRedirects
     */
    public function testToolbarIsNotInjectedOnRedirection($statusCode, $hasSession)
    {
        $response = new Response('<html><head></head><body></body></html>', $statusCode);
        $response->headers->set('X-Debug-Token', 'xxxxxxxx');
        $event = new FilterResponseEvent($this->getKernelMock(), $this->getRequestMock(false, 'html', $hasSession), HttpKernelInterface::MASTER_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock());
        $listener->onKernelResponse($event);

        $this->assertEquals('<html><head></head><body></body></html>', $response->getContent());
    }

    public function provideRedirects()
    {
        return array(
            array(301, true),
            array(302, true),
            array(301, false),
            array(302, false),
        );
    }

    /**
     * @depends testToolbarIsInjected
     */
    public function testToolbarIsNotInjectedWhenThereIsNoNoXDebugTokenResponseHeader()
    {
        $response = new Response('<html><head></head><body></body></html>');

        $event = new FilterResponseEvent($this->getKernelMock(), $this->getRequestMock(), HttpKernelInterface::MASTER_REQUEST, $response);

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

        $event = new FilterResponseEvent($this->getKernelMock(), $this->getRequestMock(), HttpKernelInterface::SUB_REQUEST, $response);

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

        $event = new FilterResponseEvent($this->getKernelMock(), $this->getRequestMock(), HttpKernelInterface::MASTER_REQUEST, $response);

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

        $event = new FilterResponseEvent($this->getKernelMock(), $this->getRequestMock(true), HttpKernelInterface::MASTER_REQUEST, $response);

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

        $event = new FilterResponseEvent($this->getKernelMock(), $this->getRequestMock(false, 'json'), HttpKernelInterface::MASTER_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock());
        $listener->onKernelResponse($event);

        $this->assertEquals('<html><head></head><body></body></html>', $response->getContent());
    }

    protected function getRequestMock($isXmlHttpRequest = false, $requestFormat = 'html', $hasSession = true)
    {
        $request = $this->getMock(
            'Symfony\Component\HttpFoundation\Request',
            array('getSession', 'isXmlHttpRequest', 'getRequestFormat'),
            array(), '', false
        );
        $request->expects($this->any())
            ->method('isXmlHttpRequest')
            ->will($this->returnValue($isXmlHttpRequest));
        $request->expects($this->any())
            ->method('getRequestFormat')
            ->will($this->returnValue($requestFormat));

        if ($hasSession) {
            $session = $this->getMock('Symfony\Component\HttpFoundation\Session\Session', array(), array(), '', false);
            $request->expects($this->any())
                ->method('getSession')
                ->will($this->returnValue($session));
        }

        return $request;
    }

    protected function getTwigMock($render = 'WDT')
    {
        $templating = $this->getMock('Twig_Environment', array(), array(), '', false);
        $templating->expects($this->any())
            ->method('render')
            ->will($this->returnValue($render));

        return $templating;
    }

    protected function getKernelMock()
    {
        return $this->getMock('Symfony\Component\HttpKernel\Kernel', array(), array(), '', false);
    }
}
