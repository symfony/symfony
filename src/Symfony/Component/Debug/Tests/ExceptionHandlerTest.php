<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Debug\Tests;

use Symfony\Component\Debug\ExceptionHandler;
use Symfony\Component\Debug\Exception\OutOfMemoryException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

class ExceptionHandlerTest extends \PHPUnit_Framework_TestCase
{
    public function testDebug()
    {
        $handler = new ExceptionHandler(false);
        $response = $handler->createResponse(new \RuntimeException('Foo'));

        $this->assertContains('<h1>Whoops, looks like something went wrong.</h1>', $response->getContent());
        $this->assertNotContains('<h2 class="block_exception clear_fix">', $response->getContent());

        $handler = new ExceptionHandler(true);
        $response = $handler->createResponse(new \RuntimeException('Foo'));

        $this->assertContains('<h1>Whoops, looks like something went wrong.</h1>', $response->getContent());
        $this->assertContains('<h2 class="block_exception clear_fix">', $response->getContent());
    }

    public function testStatusCode()
    {
        $handler = new ExceptionHandler(false);

        $response = $handler->createResponse(new \RuntimeException('Foo'));
        $this->assertEquals('500', $response->getStatusCode());
        $this->assertContains('Whoops, looks like something went wrong.', $response->getContent());

        $response = $handler->createResponse(new NotFoundHttpException('Foo'));
        $this->assertEquals('404', $response->getStatusCode());
        $this->assertContains('Sorry, the page you are looking for could not be found.', $response->getContent());
    }

    public function testHeaders()
    {
        $handler = new ExceptionHandler(false);

        $response = $handler->createResponse(new MethodNotAllowedHttpException(array('POST')));
        $this->assertEquals('405', $response->getStatusCode());
        $this->assertEquals('POST', $response->headers->get('Allow'));
    }

    public function testNestedExceptions()
    {
        $handler = new ExceptionHandler(true);
        $response = $handler->createResponse(new \RuntimeException('Foo', 0, new \RuntimeException('Bar')));
    }

    public function testHandle()
    {
        $exception = new \Exception('foo');

        if (class_exists('Symfony\Component\HttpFoundation\Response')) {
            $handler = $this->getMock('Symfony\Component\Debug\ExceptionHandler', array('createResponse'));
            $handler
                ->expects($this->exactly(2))
                ->method('createResponse')
                ->will($this->returnValue(new Response()));
        } else {
            $handler = $this->getMock('Symfony\Component\Debug\ExceptionHandler', array('sendPhpResponse'));
            $handler
                ->expects($this->exactly(2))
                ->method('sendPhpResponse');
        }

        $handler->handle($exception);

<<<<<<< HEAD
        $that = $this;
        $handler->setHandler(function ($e) use ($exception, $that) {
            $that->assertSame($exception, $e);
=======
        $handler->setHandler(function ($e) use ($exception) {
            $this->assertSame($exception, $e);
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d
        });

        $handler->handle($exception);
    }

    public function testHandleOutOfMemoryException()
    {
        $exception = new OutOfMemoryException('foo', 0, E_ERROR, __FILE__, __LINE__);

        if (class_exists('Symfony\Component\HttpFoundation\Response')) {
            $handler = $this->getMock('Symfony\Component\Debug\ExceptionHandler', array('createResponse'));
            $handler
                ->expects($this->once())
                ->method('createResponse')
                ->will($this->returnValue(new Response()));
        } else {
            $handler = $this->getMock('Symfony\Component\Debug\ExceptionHandler', array('sendPhpResponse'));
            $handler
                ->expects($this->once())
                ->method('sendPhpResponse');
        }

<<<<<<< HEAD
        $that = $this;
        $handler->setHandler(function ($e) use ($that) {
            $that->fail('OutOfMemoryException should bypass the handler');
=======
        $handler->setHandler(function ($e) {
            $this->fail('OutOfMemoryException should bypass the handler');
>>>>>>> 22cd78c4a87e94b59ad313d11b99acb50aa17b8d
        });

        $handler->handle($exception);
    }
}
