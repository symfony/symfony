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

use PHPUnit\Framework\TestCase;
use Symfony\Component\Debug\Exception\OutOfMemoryException;
use Symfony\Component\Debug\ExceptionHandler;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

require_once __DIR__.'/HeaderMock.php';

class ExceptionHandlerTest extends TestCase
{
    protected function setUp()
    {
        testHeader();
    }

    protected function tearDown()
    {
        testHeader();
    }

    public function testDebug()
    {
        $handler = new ExceptionHandler(false);

        ob_start();
        $handler->sendPhpResponse(new \RuntimeException('Foo'));
        $response = ob_get_clean();

        $this->assertStringContainsString('Whoops, looks like something went wrong.', $response);
        $this->assertStringNotContainsString('<div class="trace trace-as-html">', $response);

        $handler = new ExceptionHandler(true);

        ob_start();
        $handler->sendPhpResponse(new \RuntimeException('Foo'));
        $response = ob_get_clean();

        $this->assertStringContainsString('Whoops, looks like something went wrong.', $response);
        $this->assertStringContainsString('<div class="trace trace-as-html">', $response);
    }

    public function testStatusCode()
    {
        $handler = new ExceptionHandler(false, 'iso8859-1');

        ob_start();
        $handler->sendPhpResponse(new NotFoundHttpException('Foo'));
        $response = ob_get_clean();

        $this->assertStringContainsString('Sorry, the page you are looking for could not be found.', $response);

        $expectedHeaders = [
            ['HTTP/1.0 404', true, null],
            ['Content-Type: text/html; charset=iso8859-1', true, null],
        ];

        $this->assertSame($expectedHeaders, testHeader());
    }

    public function testHeaders()
    {
        $handler = new ExceptionHandler(false, 'iso8859-1');

        ob_start();
        $handler->sendPhpResponse(new MethodNotAllowedHttpException(['POST']));
        ob_get_clean();

        $expectedHeaders = [
            ['HTTP/1.0 405', true, null],
            ['Allow: POST', false, null],
            ['Content-Type: text/html; charset=iso8859-1', true, null],
        ];

        $this->assertSame($expectedHeaders, testHeader());
    }

    public function testNestedExceptions()
    {
        $handler = new ExceptionHandler(true);
        ob_start();
        $handler->sendPhpResponse(new \RuntimeException('Foo', 0, new \RuntimeException('Bar')));
        $response = ob_get_clean();

        $this->assertStringMatchesFormat('%A<p class="break-long-words trace-message">Foo</p>%A<p class="break-long-words trace-message">Bar</p>%A', $response);
    }

    public function testHandle()
    {
        $handler = new ExceptionHandler(true);
        ob_start();

        $handler->handle(new \Exception('foo'));

        $this->assertThatTheExceptionWasOutput(ob_get_clean(), \Exception::class, 'Exception', 'foo');
    }

    public function testHandleWithACustomHandlerThatOutputsSomething()
    {
        $handler = new ExceptionHandler(true);
        ob_start();
        $handler->setHandler(function () {
            echo 'ccc';
        });

        $handler->handle(new \Exception());
        ob_end_flush(); // Necessary because of this PHP bug : https://bugs.php.net/76563
        $this->assertSame('ccc', ob_get_clean());
    }

    public function testHandleWithACustomHandlerThatOutputsNothing()
    {
        $handler = new ExceptionHandler(true);
        $handler->setHandler(function () {});

        $handler->handle(new \Exception('ccc'));

        $this->assertThatTheExceptionWasOutput(ob_get_clean(), \Exception::class, 'Exception', 'ccc');
    }

    public function testHandleWithACustomHandlerThatFails()
    {
        $handler = new ExceptionHandler(true);
        $handler->setHandler(function () {
            throw new \RuntimeException();
        });

        $handler->handle(new \Exception('ccc'));

        $this->assertThatTheExceptionWasOutput(ob_get_clean(), \Exception::class, 'Exception', 'ccc');
    }

    public function testHandleOutOfMemoryException()
    {
        $handler = new ExceptionHandler(true);
        ob_start();
        $handler->setHandler(function () {
            $this->fail('OutOfMemoryException should bypass the handler');
        });

        $handler->handle(new OutOfMemoryException('foo', 0, \E_ERROR, __FILE__, __LINE__));

        $this->assertThatTheExceptionWasOutput(ob_get_clean(), OutOfMemoryException::class, 'OutOfMemoryException', 'foo');
    }

    private function assertThatTheExceptionWasOutput($content, $expectedClass, $expectedTitle, $expectedMessage)
    {
        $this->assertStringContainsString(sprintf('<span class="exception_title"><abbr title="%s">%s</abbr></span>', $expectedClass, $expectedTitle), $content);
        $this->assertStringContainsString(sprintf('<p class="break-long-words trace-message">%s</p>', $expectedMessage), $content);
    }
}
