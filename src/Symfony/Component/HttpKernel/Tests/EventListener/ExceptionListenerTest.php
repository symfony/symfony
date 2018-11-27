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
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpKernel\EventListener\ExceptionListener;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;
use Symfony\Component\HttpKernel\Tests\Logger;

/**
 * ExceptionListenerTest.
 *
 * @author Robert Schönthal <seroscho@googlemail.com>
 *
 * @group time-sensitive
 */
class ExceptionListenerTest extends TestCase
{
    public function testConstruct()
    {
        $logger = new TestLogger();
        $l = new ExceptionListener('foo', $logger);

        $_logger = new \ReflectionProperty(\get_class($l), 'logger');
        $_logger->setAccessible(true);
        $_controller = new \ReflectionProperty(\get_class($l), 'controller');
        $_controller->setAccessible(true);

        $this->assertSame($logger, $_logger->getValue($l));
        $this->assertSame('foo', $_controller->getValue($l));
    }

    /**
     * @dataProvider provider
     */
    public function testHandleWithoutLogger($event, $event2)
    {
        $this->iniSet('error_log', file_exists('/dev/null') ? '/dev/null' : 'nul');

        $l = new ExceptionListener('foo');
        $l->logKernelException($event);
        $l->onKernelException($event);

        $this->assertEquals(new Response('foo'), $event->getResponse());

        try {
            $l->logKernelException($event2);
            $l->onKernelException($event2);
            $this->fail('RuntimeException expected');
        } catch (\RuntimeException $e) {
            $this->assertSame('bar', $e->getMessage());
            $this->assertSame('foo', $e->getPrevious()->getMessage());
        }
    }

    /**
     * @dataProvider provider
     */
    public function testHandleWithLogger($event, $event2)
    {
        $logger = new TestLogger();

        $l = new ExceptionListener('foo', $logger);
        $l->logKernelException($event);
        $l->onKernelException($event);

        $this->assertEquals(new Response('foo'), $event->getResponse());

        try {
            $l->logKernelException($event2);
            $l->onKernelException($event2);
            $this->fail('RuntimeException expected');
        } catch (\RuntimeException $e) {
            $this->assertSame('bar', $e->getMessage());
            $this->assertSame('foo', $e->getPrevious()->getMessage());
        }

        $this->assertEquals(3, $logger->countErrors());
        $this->assertCount(3, $logger->getLogs('critical'));
    }

    public function provider()
    {
        if (!class_exists('Symfony\Component\HttpFoundation\Request')) {
            return array(array(null, null));
        }

        $request = new Request();
        $exception = new \Exception('foo');
        $event = new GetResponseForExceptionEvent(new TestKernel(), $request, HttpKernelInterface::MASTER_REQUEST, $exception);
        $event2 = new GetResponseForExceptionEvent(new TestKernelThatThrowsException(), $request, HttpKernelInterface::MASTER_REQUEST, $exception);

        return array(
            array($event, $event2),
        );
    }

    public function testSubRequestFormat()
    {
        $listener = new ExceptionListener('foo', $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock());

        $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\HttpKernelInterface')->getMock();
        $kernel->expects($this->once())->method('handle')->will($this->returnCallback(function (Request $request) {
            return new Response($request->getRequestFormat());
        }));

        $request = Request::create('/');
        $request->setRequestFormat('xml');

        $event = new GetResponseForExceptionEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST, new \Exception('foo'));
        $listener->onKernelException($event);

        $response = $event->getResponse();
        $this->assertEquals('xml', $response->getContent());
    }

    public function testCSPHeaderIsRemoved()
    {
        $dispatcher = new EventDispatcher();
        $kernel = $this->getMockBuilder('Symfony\Component\HttpKernel\HttpKernelInterface')->getMock();
        $kernel->expects($this->once())->method('handle')->will($this->returnCallback(function (Request $request) {
            return new Response($request->getRequestFormat());
        }));

        $listener = new ExceptionListener('foo', $this->getMockBuilder('Psr\Log\LoggerInterface')->getMock(), true);

        $dispatcher->addSubscriber($listener);

        $request = Request::create('/');
        $event = new GetResponseForExceptionEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST, new \Exception('foo'));
        $dispatcher->dispatch(KernelEvents::EXCEPTION, $event);

        $response = new Response('', 200, array('content-security-policy' => "style-src 'self'"));
        $this->assertTrue($response->headers->has('content-security-policy'));

        $event = new FilterResponseEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST, $response);
        $dispatcher->dispatch(KernelEvents::RESPONSE, $event);

        $this->assertFalse($response->headers->has('content-security-policy'), 'CSP header has been removed');
        $this->assertFalse($dispatcher->hasListeners(KernelEvents::RESPONSE), 'CSP removal listener has been removed');
    }

    public function testNullController()
    {
        $listener = new ExceptionListener(null);
        $kernel = $this->getMockBuilder(HttpKernelInterface::class)->getMock();
        $kernel->expects($this->once())->method('handle')->will($this->returnCallback(function (Request $request) {
            $controller = $request->attributes->get('_controller');

            return $controller();
        }));
        $request = Request::create('/');
        $event = new GetResponseForExceptionEvent($kernel, $request, HttpKernelInterface::MASTER_REQUEST, new \Exception('foo'));

        $listener->onKernelException($event);
        $this->assertNull($event->getResponse());

        $listener->onKernelException($event);
        $this->assertContains('Whoops, looks like something went wrong.', $event->getResponse()->getContent());
    }
}

class TestLogger extends Logger implements DebugLoggerInterface
{
    public function countErrors()
    {
        return \count($this->logs['critical']);
    }
}

class TestKernel implements HttpKernelInterface
{
    public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = true)
    {
        return new Response('foo');
    }
}

class TestKernelThatThrowsException implements HttpKernelInterface
{
    public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = true)
    {
        throw new \RuntimeException('bar');
    }
}
