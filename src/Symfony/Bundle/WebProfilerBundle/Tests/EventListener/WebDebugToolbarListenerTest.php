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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

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

        $m->invoke($listener, $response, Request::create('/'));
        $this->assertEquals($expected, $response->getContent());
    }

    public function getInjectToolbarTests()
    {
        return array(
            array('<!DOCTYPE html><title>title</title>', "<!DOCTYPE html><title>title</title>\nWDT\n"),
            array('<!DOCTYPE html><title>title</title><body></body>', "<!DOCTYPE html><title>title</title><body>\nWDT\n</body>"),
            array('<!DOCTYPE html><html><title>title</title></html>', "<!DOCTYPE html><html><title>title</title>\nWDT\n</html>"),
            array('<!DOCTYPE html><html><title>title</title><body></body></html>', "<!DOCTYPE html><html><title>title</title><body>\nWDT\n</body></html>"),
            array('<html></html>', "<html>\nWDT\n</html>"),
            array('<body></body>', "<body>\nWDT\n</body>"),
            array('<html><body></body></html>', "<html><body>\nWDT\n</body></html>"),
            array(
                '<!DOCTYPE html><html><title>title</title><body><script>/*<![CDATA[*/<title></body></html>/*]]>*/</script></body></html>',
                "<!DOCTYPE html><html><title>title</title><body><script>/*<![CDATA[*/<title></body></html>/*]]>*/</script>\nWDT\n</body></html>",
            ),
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
        $response = new Response('<!DOCTYPE html><title>title</title>');
        $response->headers->set('X-Debug-Token', 'xxxxxxxx');

        $event = new FilterResponseEvent($this->getKernelMock(), $this->getRequestMock(), HttpKernelInterface::MASTER_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock());
        $listener->onKernelResponse($event);

        $this->assertEquals("<!DOCTYPE html><title>title</title>\nWDT\n", $response->getContent());
    }

    /**
     * @depends testToolbarIsInjected
     */
    public function testToolbarIsNotInjectedOnContentDispositionAttachment()
    {
        $response = new Response('<!DOCTYPE html><title>title</title>');
        $response->headers->set('Content-Disposition', 'attachment; filename=test.html');
        $event = new FilterResponseEvent($this->getKernelMock(), $this->getRequestMock(false, 'html'), HttpKernelInterface::MASTER_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock());
        $listener->onKernelResponse($event);

        $this->assertEquals('<!DOCTYPE html><title>title</title>', $response->getContent());
    }

    /**
     * @depends testToolbarIsInjected
     * @dataProvider provideRedirects
     */
    public function testToolbarIsNotInjectedOnRedirection($statusCode, $hasSession)
    {
        $response = new Response('<!DOCTYPE html><title>title</title>', $statusCode);
        $response->headers->set('X-Debug-Token', 'xxxxxxxx');
        $event = new FilterResponseEvent($this->getKernelMock(), $this->getRequestMock(false, 'html', $hasSession), HttpKernelInterface::MASTER_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock());
        $listener->onKernelResponse($event);

        $this->assertEquals('<!DOCTYPE html><title>title</title>', $response->getContent());
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
        $response = new Response('<!DOCTYPE html><title>title</title>');

        $event = new FilterResponseEvent($this->getKernelMock(), $this->getRequestMock(), HttpKernelInterface::MASTER_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock());
        $listener->onKernelResponse($event);

        $this->assertEquals('<!DOCTYPE html><title>title</title>', $response->getContent());
    }

    /**
     * @depends testToolbarIsInjected
     */
    public function testToolbarIsNotInjectedWhenOnSubRequest()
    {
        $response = new Response('<!DOCTYPE html><title>title</title>');
        $response->headers->set('X-Debug-Token', 'xxxxxxxx');

        $event = new FilterResponseEvent($this->getKernelMock(), $this->getRequestMock(), HttpKernelInterface::SUB_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock());
        $listener->onKernelResponse($event);

        $this->assertEquals('<!DOCTYPE html><title>title</title>', $response->getContent());
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
        $response = new Response('<!DOCTYPE html><title>title</title>');
        $response->headers->set('X-Debug-Token', 'xxxxxxxx');

        $event = new FilterResponseEvent($this->getKernelMock(), $this->getRequestMock(true), HttpKernelInterface::MASTER_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock());
        $listener->onKernelResponse($event);

        $this->assertEquals('<!DOCTYPE html><title>title</title>', $response->getContent());
    }

    /**
     * @depends testToolbarIsInjected
     */
    public function testToolbarIsNotInjectedOnNonHtmlRequests()
    {
        $response = new Response('<!DOCTYPE html><title>title</title>');
        $response->headers->set('X-Debug-Token', 'xxxxxxxx');

        $event = new FilterResponseEvent($this->getKernelMock(), $this->getRequestMock(false, 'json'), HttpKernelInterface::MASTER_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock());
        $listener->onKernelResponse($event);

        $this->assertEquals('<!DOCTYPE html><title>title</title>', $response->getContent());
    }

    public function testXDebugUrlHeader()
    {
        $response = new Response();
        $response->headers->set('X-Debug-Token', 'xxxxxxxx');

        $urlGenerator = $this->getUrlGeneratorMock();
        $urlGenerator
            ->expects($this->once())
            ->method('generate')
            ->with('_profiler', array('token' => 'xxxxxxxx'), UrlGeneratorInterface::ABSOLUTE_URL)
            ->will($this->returnValue('http://mydomain.com/_profiler/xxxxxxxx'))
        ;

        $event = new FilterResponseEvent($this->getKernelMock(), $this->getRequestMock(), HttpKernelInterface::MASTER_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock(), false, WebDebugToolbarListener::ENABLED, 'bottom', $urlGenerator);
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
            ->with('_profiler', array('token' => 'xxxxxxxx'))
            ->will($this->throwException(new \Exception('foo')))
        ;

        $event = new FilterResponseEvent($this->getKernelMock(), $this->getRequestMock(), HttpKernelInterface::MASTER_REQUEST, $response);

        $listener = new WebDebugToolbarListener($this->getTwigMock(), false, WebDebugToolbarListener::ENABLED, 'bottom', $urlGenerator);
        $listener->onKernelResponse($event);

        $this->assertEquals('Exception: foo', $response->headers->get('X-Debug-Error'));
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

    protected function getUrlGeneratorMock()
    {
        return $this->getMock('Symfony\Component\Routing\Generator\UrlGeneratorInterface');
    }

    protected function getKernelMock()
    {
        return $this->getMock('Symfony\Component\HttpKernel\Kernel', array(), array(), '', false);
    }
}
