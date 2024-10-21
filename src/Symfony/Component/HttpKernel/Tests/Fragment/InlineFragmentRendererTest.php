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
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ArgumentResolverInterface;
use Symfony\Component\HttpKernel\Controller\ControllerReference;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Fragment\InlineFragmentRenderer;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @group time-sensitive
 */
class InlineFragmentRendererTest extends TestCase
{
    public function testRender()
    {
        $strategy = new InlineFragmentRenderer($this->getKernel(new Response('foo')));

        $this->assertEquals('foo', $strategy->render('/', Request::create('/'))->getContent());
    }

    public function testRenderWithControllerReference()
    {
        $strategy = new InlineFragmentRenderer($this->getKernel(new Response('foo')));

        $this->assertEquals('foo', $strategy->render(new ControllerReference('main_controller', [], []), Request::create('/'))->getContent());
    }

    public function testRenderWithObjectsAsAttributes()
    {
        $object = new \stdClass();

        $subRequest = Request::create('/_fragment?_path=_format%3Dhtml%26_locale%3Den%26_controller%3Dmain_controller');
        $subRequest->attributes->replace(['object' => $object, '_format' => 'html', '_controller' => 'main_controller', '_locale' => 'en']);
        $subRequest->headers->set('x-forwarded-for', ['127.0.0.1']);
        $subRequest->headers->set('forwarded', ['for="127.0.0.1";host="localhost";proto=http']);
        $subRequest->server->set('HTTP_X_FORWARDED_FOR', '127.0.0.1');
        $subRequest->server->set('HTTP_FORWARDED', 'for="127.0.0.1";host="localhost";proto=http');

        $strategy = new InlineFragmentRenderer($this->getKernelExpectingRequest($subRequest));

        $this->assertSame('foo', $strategy->render(new ControllerReference('main_controller', ['object' => $object], []), Request::create('/'))->getContent());
    }

    public function testRenderWithTrustedHeaderDisabled()
    {
        Request::setTrustedProxies([], 0);

        $expectedSubRequest = Request::create('/');
        $expectedSubRequest->headers->set('x-forwarded-for', ['127.0.0.1']);
        $expectedSubRequest->server->set('HTTP_X_FORWARDED_FOR', '127.0.0.1');

        $strategy = new InlineFragmentRenderer($this->getKernelExpectingRequest($expectedSubRequest));
        $this->assertSame('foo', $strategy->render('/', Request::create('/'))->getContent());

        Request::setTrustedProxies([], -1);
    }

    public function testRenderExceptionNoIgnoreErrors()
    {
        $this->expectException(\RuntimeException::class);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->never())->method('dispatch');

        $strategy = new InlineFragmentRenderer($this->getKernel(new \RuntimeException('foo')), $dispatcher);

        $this->assertEquals('foo', $strategy->render('/', Request::create('/'))->getContent());
    }

    public function testRenderExceptionIgnoreErrors()
    {
        $exception = new \RuntimeException('foo');
        $kernel = $this->getKernel($exception);
        $request = Request::create('/');
        $expectedEvent = new ExceptionEvent($kernel, $request, $kernel::SUB_REQUEST, $exception);
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->once())->method('dispatch')->with($expectedEvent, KernelEvents::EXCEPTION);

        $strategy = new InlineFragmentRenderer($kernel, $dispatcher);

        $this->assertEmpty($strategy->render('/', $request, ['ignore_errors' => true])->getContent());
    }

    public function testRenderExceptionIgnoreErrorsWithAlt()
    {
        $strategy = new InlineFragmentRenderer($this->getKernel($this->returnCallback(function () {
            static $firstCall = true;

            if ($firstCall) {
                $firstCall = false;

                throw new \RuntimeException('foo');
            }

            return new Response('bar');
        })));

        $this->assertEquals('bar', $strategy->render('/', Request::create('/'), ['ignore_errors' => true, 'alt' => '/foo'])->getContent());
    }

    private function getKernel($returnValue)
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $mocker = $kernel
            ->expects($this->any())
            ->method('handle')
        ;

        if ($returnValue instanceof \Exception) {
            $mocker->willThrowException($returnValue);
        } else {
            $mocker->willReturn(...(\is_array($returnValue) ? $returnValue : [$returnValue]));
        }

        return $kernel;
    }

    public function testExceptionInSubRequestsDoesNotMangleOutputBuffers()
    {
        $controllerResolver = $this->createMock(ControllerResolverInterface::class);
        $controllerResolver
            ->expects($this->once())
            ->method('getController')
            ->willReturn(function () {
                ob_start();
                echo 'bar';
                throw new \RuntimeException();
            })
        ;

        $argumentResolver = $this->createMock(ArgumentResolverInterface::class);
        $argumentResolver
            ->expects($this->once())
            ->method('getArguments')
            ->willReturn([])
        ;

        $kernel = new HttpKernel(new EventDispatcher(), $controllerResolver, new RequestStack(), $argumentResolver);
        $renderer = new InlineFragmentRenderer($kernel);

        // simulate a main request with output buffering
        ob_start();
        echo 'Foo';

        // simulate a sub-request with output buffering and an exception
        $renderer->render('/', Request::create('/'), ['ignore_errors' => true]);

        $this->assertEquals('Foo', ob_get_clean());
    }

    public function testLocaleAndFormatAreKeptInSubrequest()
    {
        $expectedSubRequest = Request::create('/');
        $expectedSubRequest->attributes->set('_format', 'foo');
        $expectedSubRequest->setLocale('fr');
        if (Request::HEADER_X_FORWARDED_FOR & Request::getTrustedHeaderSet()) {
            $expectedSubRequest->headers->set('x-forwarded-for', ['127.0.0.1']);
            $expectedSubRequest->server->set('HTTP_X_FORWARDED_FOR', '127.0.0.1');
        }
        $expectedSubRequest->headers->set('forwarded', ['for="127.0.0.1";host="localhost";proto=http']);
        $expectedSubRequest->server->set('HTTP_FORWARDED', 'for="127.0.0.1";host="localhost";proto=http');

        $strategy = new InlineFragmentRenderer($this->getKernelExpectingRequest($expectedSubRequest));

        $request = Request::create('/');
        $request->attributes->set('_format', 'foo');
        $request->setLocale('fr');
        $strategy->render('/', $request);
    }

    public function testESIHeaderIsKeptInSubrequest()
    {
        $expectedSubRequest = Request::create('/');
        $expectedSubRequest->headers->set('Surrogate-Capability', 'abc="ESI/1.0"');

        if (Request::HEADER_X_FORWARDED_FOR & Request::getTrustedHeaderSet()) {
            $expectedSubRequest->headers->set('x-forwarded-for', ['127.0.0.1']);
            $expectedSubRequest->server->set('HTTP_X_FORWARDED_FOR', '127.0.0.1');
        }
        $expectedSubRequest->headers->set('forwarded', ['for="127.0.0.1";host="localhost";proto=http']);
        $expectedSubRequest->server->set('HTTP_FORWARDED', 'for="127.0.0.1";host="localhost";proto=http');

        $strategy = new InlineFragmentRenderer($this->getKernelExpectingRequest($expectedSubRequest));

        $request = Request::create('/');
        $request->headers->set('Surrogate-Capability', 'abc="ESI/1.0"');
        $strategy->render('/', $request);
    }

    public function testESIHeaderIsKeptInSubrequestWithTrustedHeaderDisabled()
    {
        Request::setTrustedProxies([], Request::HEADER_FORWARDED);

        $this->testESIHeaderIsKeptInSubrequest();

        Request::setTrustedProxies([], -1);
    }

    public function testHeadersPossiblyResultingIn304AreNotAssignedToSubrequest()
    {
        $expectedSubRequest = Request::create('/');
        $expectedSubRequest->headers->set('x-forwarded-for', ['127.0.0.1']);
        $expectedSubRequest->headers->set('forwarded', ['for="127.0.0.1";host="localhost";proto=http']);
        $expectedSubRequest->server->set('HTTP_X_FORWARDED_FOR', '127.0.0.1');
        $expectedSubRequest->server->set('HTTP_FORWARDED', 'for="127.0.0.1";host="localhost";proto=http');

        $strategy = new InlineFragmentRenderer($this->getKernelExpectingRequest($expectedSubRequest));
        $request = Request::create('/', 'GET', [], [], [], ['HTTP_IF_MODIFIED_SINCE' => 'Fri, 01 Jan 2016 00:00:00 GMT', 'HTTP_IF_NONE_MATCH' => '*']);
        $strategy->render('/', $request);
    }

    public function testFirstTrustedProxyIsSetAsRemote()
    {
        Request::setTrustedProxies(['1.1.1.1'], -1);

        $expectedSubRequest = Request::create('/');
        $expectedSubRequest->headers->set('Surrogate-Capability', 'abc="ESI/1.0"');
        $expectedSubRequest->server->set('REMOTE_ADDR', '127.0.0.1');
        $expectedSubRequest->headers->set('x-forwarded-for', ['127.0.0.1']);
        $expectedSubRequest->headers->set('forwarded', ['for="127.0.0.1";host="localhost";proto=http']);
        $expectedSubRequest->server->set('HTTP_X_FORWARDED_FOR', '127.0.0.1');
        $expectedSubRequest->server->set('HTTP_FORWARDED', 'for="127.0.0.1";host="localhost";proto=http');

        $strategy = new InlineFragmentRenderer($this->getKernelExpectingRequest($expectedSubRequest));

        $request = Request::create('/');
        $request->headers->set('Surrogate-Capability', 'abc="ESI/1.0"');
        $strategy->render('/', $request);

        Request::setTrustedProxies([], -1);
    }

    public function testIpAddressOfRangedTrustedProxyIsSetAsRemote()
    {
        $expectedSubRequest = Request::create('/');
        $expectedSubRequest->headers->set('Surrogate-Capability', 'abc="ESI/1.0"');
        $expectedSubRequest->server->set('REMOTE_ADDR', '127.0.0.1');
        $expectedSubRequest->headers->set('x-forwarded-for', ['127.0.0.1']);
        $expectedSubRequest->headers->set('forwarded', ['for="127.0.0.1";host="localhost";proto=http']);
        $expectedSubRequest->server->set('HTTP_X_FORWARDED_FOR', '127.0.0.1');
        $expectedSubRequest->server->set('HTTP_FORWARDED', 'for="127.0.0.1";host="localhost";proto=http');

        Request::setTrustedProxies(['1.1.1.1/24'], -1);

        $strategy = new InlineFragmentRenderer($this->getKernelExpectingRequest($expectedSubRequest));

        $request = Request::create('/');
        $request->headers->set('Surrogate-Capability', 'abc="ESI/1.0"');
        $strategy->render('/', $request);

        Request::setTrustedProxies([], -1);
    }

    /**
     * Creates a Kernel expecting a request equals to $request.
     */
    private function getKernelExpectingRequest(Request $expectedRequest)
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $kernel
            ->expects($this->once())
            ->method('handle')
            ->with($this->callback(function (Request $request) use ($expectedRequest) {
                $expectedRequest->server->remove('REQUEST_TIME_FLOAT');
                $request->server->remove('REQUEST_TIME_FLOAT');

                return $expectedRequest == $request;
            }))
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
