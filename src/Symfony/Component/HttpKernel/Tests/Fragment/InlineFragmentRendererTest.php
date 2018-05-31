<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Fragment;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Controller\ControllerReference;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\Fragment\InlineFragmentRenderer;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\EventDispatcher\EventDispatcher;

class InlineFragmentRendererTest extends TestCase
{
    public function testRender()
    {
        $strategy = new InlineFragmentRenderer($this->getKernel($this->returnValue(new Response('foo'))));

        $this->assertEquals('foo', $strategy->render('/', Request::create('/'))->getContent());
    }

    public function testRenderWithControllerReference()
    {
        $strategy = new InlineFragmentRenderer($this->getKernel($this->returnValue(new Response('foo'))));

        $this->assertEquals('foo', $strategy->render(new ControllerReference('main_controller', array(), array()), Request::create('/'))->getContent());
    }

    public function testRenderWithObjectsAsAttributes()
    {
        $object = new \stdClass();

        $subRequest = Request::create('/_fragment?_path=_format%3Dhtml%26_locale%3Den%26_controller%3Dmain_controller');
        $subRequest->attributes->replace(array('object' => $object, '_format' => 'html', '_controller' => 'main_controller', '_locale' => 'en'));
        $subRequest->headers->set('x-forwarded-for', array('127.0.0.1'));
        $subRequest->server->set('HTTP_X_FORWARDED_FOR', '127.0.0.1');

        $strategy = new InlineFragmentRenderer($this->getKernelExpectingRequest($subRequest));

        $this->assertSame('foo', $strategy->render(new ControllerReference('main_controller', array('object' => $object), array()), Request::create('/'))->getContent());
    }

    public function testRenderWithTrustedHeaderDisabled()
    {
        Request::setTrustedProxies(array(), 0);

        $strategy = new InlineFragmentRenderer($this->getKernelExpectingRequest(Request::create('/')));
        $this->assertSame('foo', $strategy->render('/', Request::create('/'))->getContent());

        Request::setTrustedProxies(array(), -1);
    }

    /**
     * @expectedException \RuntimeException
     */
    public function testRenderExceptionNoIgnoreErrors()
    {
        $dispatcher = $this->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcherInterface')->getMock();
        $dispatcher->expects($this->never())->method('dispatch');

        $strategy = new InlineFragmentRenderer($this->getKernel($this->throwException(new \RuntimeException('foo'))), $dispatcher);

        $this->assertEquals('foo', $strategy->render('/', Request::create('/'))->getContent());
    }

    public function testRenderExceptionIgnoreErrors()
    {
        $dispatcher = $this->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcherInterface')->getMock();
        $dispatcher->expects($this->once())->method('dispatch')->with(KernelEvents::EXCEPTION);

        $strategy = new InlineFragmentRenderer($this->getKernel($this->throwException(new \RuntimeException('foo'))), $dispatcher);

        $this->assertEmpty($strategy->render('/', Request::create('/'), array('ignore_errors' => true))->getContent());
    }

    public function testRenderExceptionIgnoreErrorsWithAlt()
    {
        $strategy = new InlineFragmentRenderer($this->getKernel($this->onConsecutiveCalls(
            $this->throwException(new \RuntimeException('foo')),
            $this->returnValue(new Response('bar'))
        )));

        $this->assertEquals('bar', $strategy->render('/', Request::create('/'), array('ignore_errors' => true, 'alt' => '/foo'))->getContent());
    }

    private function getKernel($returnValue)
    {
        $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\HttpKernelInterface')->getMock();
        $kernel
            ->expects($this->any())
            ->method('handle')
            ->will($returnValue)
        ;

        return $kernel;
    }

    public function testExceptionInSubRequestsDoesNotMangleOutputBuffers()
    {
        $controllerResolver = $this->getMockBuilder('Symfony\\Component\\HttpKernel\\Controller\\ControllerResolverInterface')->getMock();
        $controllerResolver
            ->expects($this->once())
            ->method('getController')
            ->will($this->returnValue(function () {
                ob_start();
                echo 'bar';
                throw new \RuntimeException();
            }))
        ;

        $argumentResolver = $this->getMockBuilder('Symfony\\Component\\HttpKernel\\Controller\\ArgumentResolverInterface')->getMock();
        $argumentResolver
            ->expects($this->once())
            ->method('getArguments')
            ->will($this->returnValue(array()))
        ;

        $kernel = new HttpKernel(new EventDispatcher(), $controllerResolver, new RequestStack(), $argumentResolver);
        $renderer = new InlineFragmentRenderer($kernel);

        // simulate a main request with output buffering
        ob_start();
        echo 'Foo';

        // simulate a sub-request with output buffering and an exception
        $renderer->render('/', Request::create('/'), array('ignore_errors' => true));

        $this->assertEquals('Foo', ob_get_clean());
    }

    public function testESIHeaderIsKeptInSubrequest()
    {
        $expectedSubRequest = Request::create('/');
        $expectedSubRequest->headers->set('Surrogate-Capability', 'abc="ESI/1.0"');

        if (Request::HEADER_X_FORWARDED_FOR & Request::getTrustedHeaderSet()) {
            $expectedSubRequest->headers->set('x-forwarded-for', array('127.0.0.1'));
            $expectedSubRequest->server->set('HTTP_X_FORWARDED_FOR', '127.0.0.1');
        }

        $strategy = new InlineFragmentRenderer($this->getKernelExpectingRequest($expectedSubRequest));

        $request = Request::create('/');
        $request->headers->set('Surrogate-Capability', 'abc="ESI/1.0"');
        $strategy->render('/', $request);
    }

    public function testESIHeaderIsKeptInSubrequestWithTrustedHeaderDisabled()
    {
        Request::setTrustedProxies(array(), 0);

        $this->testESIHeaderIsKeptInSubrequest();

        Request::setTrustedProxies(array(), -1);
    }

    public function testHeadersPossiblyResultingIn304AreNotAssignedToSubrequest()
    {
        $expectedSubRequest = Request::create('/');
        $expectedSubRequest->headers->set('x-forwarded-for', array('127.0.0.1'));
        $expectedSubRequest->server->set('HTTP_X_FORWARDED_FOR', '127.0.0.1');

        $strategy = new InlineFragmentRenderer($this->getKernelExpectingRequest($expectedSubRequest));
        $request = Request::create('/', 'GET', array(), array(), array(), array('HTTP_IF_MODIFIED_SINCE' => 'Fri, 01 Jan 2016 00:00:00 GMT', 'HTTP_IF_NONE_MATCH' => '*'));
        $strategy->render('/', $request);
    }

    public function testFirstTrustedProxyIsSetAsRemote()
    {
        Request::setTrustedProxies(array('1.1.1.1'), -1);

        $expectedSubRequest = Request::create('/');
        $expectedSubRequest->headers->set('Surrogate-Capability', 'abc="ESI/1.0"');
        $expectedSubRequest->server->set('REMOTE_ADDR', '1.1.1.1');
        $expectedSubRequest->headers->set('x-forwarded-for', array('127.0.0.1'));
        $expectedSubRequest->server->set('HTTP_X_FORWARDED_FOR', '127.0.0.1');

        $strategy = new InlineFragmentRenderer($this->getKernelExpectingRequest($expectedSubRequest));

        $request = Request::create('/');
        $request->headers->set('Surrogate-Capability', 'abc="ESI/1.0"');
        $strategy->render('/', $request);

        Request::setTrustedProxies(array(), -1);
    }

    /**
     * Creates a Kernel expecting a request equals to $request
     * Allows delta in comparison in case REQUEST_TIME changed by 1 second.
     */
    private function getKernelExpectingRequest(Request $request, $strict = false)
    {
        $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\HttpKernelInterface')->getMock();
        $kernel
            ->expects($this->once())
            ->method('handle')
            ->with($this->equalTo($request, 1))
            ->willReturn(new Response('foo'));

        return $kernel;
    }
}

class Bar
{
    public $bar = 'bar';

    public function getBar()
    {
        return $this->bar;
    }
}
