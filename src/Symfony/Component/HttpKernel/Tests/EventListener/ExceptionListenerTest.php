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

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\EventListener\ExceptionListener;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Tests\Logger;

/**
 * ExceptionListenerTest.
 *
 * @author Robert Schönthal <seroscho@googlemail.com>
 *
 * @group time-sensitive
 */
class ExceptionListenerTest extends \PHPUnit_Framework_TestCase
{
    public function testConstruct()
    {
        $logger = new TestLogger();
        $l = new ExceptionListener('foo', $logger);

        $_logger = new \ReflectionProperty(get_class($l), 'logger');
        $_logger->setAccessible(true);
        $_controller = new \ReflectionProperty(get_class($l), 'controller');
        $_controller->setAccessible(true);

        $this->assertSame($logger, $_logger->getValue($l));
        $this->assertSame('foo', $_controller->getValue($l));
    }

    public function testHandleHttpExceptionThrownInListener()
    {
        // store the current error_log, and disable it temporarily
        $errorLog = ini_set('error_log', file_exists('/dev/null') ? '/dev/null' : 'nul');

        $listener = new ExceptionListener('foo');

        $dispatcher = new EventDispatcher();
        $dispatcher->addSubscriber($listener);
        $dispatcher->addListener(KernelEvents::REQUEST, function ($event) {
            throw new HttpException(1337);
        });

        $kernel = new HttpKernel($dispatcher, $this->getTestResolver());

        try {
            $kernel->handle(new Request(), HttpKernelInterface::MASTER_REQUEST);
            $this->fail('HttpKernel::handle is expected to throw HttpException');
        } catch (HttpException $exception) {
            $this->assertEquals(1337, $exception->getStatusCode());
        }

        // restore the old error_log
        ini_set('error_log', $errorLog);
    }

    /**
     * @dataProvider provider
     */
    public function testHandleWithoutLogger($event, $event2)
    {
        $this->iniSet('error_log', file_exists('/dev/null') ? '/dev/null' : 'nul');

        $l = new ExceptionListener('foo');
        $l->onKernelException($event);

        $this->assertEquals(new Response('foo'), $event->getResponse());

        try {
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
        $l->onKernelException($event);

        $this->assertEquals(new Response('foo'), $event->getResponse());

        try {
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
        $event = new GetResponseForExceptionEvent(new TestKernel(), $request, 'foo', $exception);
        $event2 = new GetResponseForExceptionEvent(new TestKernelThatThrowsException(), $request, 'foo', $exception);

        return array(
            array($event, $event2),
        );
    }

    public function testSubRequestFormat()
    {
        $listener = new ExceptionListener('foo', $this->getMock('Psr\Log\LoggerInterface'));

        $kernel = $this->getMock('Symfony\Component\HttpKernel\HttpKernelInterface');
        $kernel->expects($this->once())->method('handle')->will($this->returnCallback(function (Request $request) {
            return new Response($request->getRequestFormat());
        }));

        $request = Request::create('/');
        $request->setRequestFormat('xml');

        $event = new GetResponseForExceptionEvent($kernel, $request, 'foo', new \Exception('foo'));
        $listener->onKernelException($event);

        $response = $event->getResponse();
        $this->assertEquals('xml', $response->getContent());
    }

    protected function getTestResolver()
    {
        $controller = function () { return new Response('foo'); };

        $resolver = $this->getMock('Symfony\\Component\\HttpKernel\\Controller\\ControllerResolverInterface');
        $resolver->expects($this->any())
            ->method('getController')
            ->will($this->returnValue($controller));
        $resolver->expects($this->any())
            ->method('getArguments')
            ->will($this->returnValue(array()));

        return $resolver;
    }
}

class TestLogger extends Logger implements DebugLoggerInterface
{
    public function countErrors()
    {
        return count($this->logs['critical']);
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
