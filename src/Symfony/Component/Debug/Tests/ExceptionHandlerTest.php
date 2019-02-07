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

        $this->assertContains('Whoops, looks like something went wrong.', $response);
        $this->assertNotContains('<div class="trace trace-as-html">', $response);

        $handler = new ExceptionHandler(true);

        ob_start();
        $handler->sendPhpResponse(new \RuntimeException('Foo'));
        $response = ob_get_clean();

        $this->assertContains('<h1 class="break-long-words exception-message">Foo</h1>', $response);
        $this->assertContains('<div class="trace trace-as-html">', $response);

        // taken from https://www.owasp.org/index.php/Cross-site_Scripting_(XSS)
        $htmlWithXss = '<body onload=alert(\'test1\')> <b onmouseover=alert(\'Wufff!\')>click me!</b> <img src="j&#X41vascript:alert(\'test2\')"> <meta http-equiv="refresh"
content="0;url=data:text/html;base64,PHNjcmlwdD5hbGVydCgndGVzdDMnKTwvc2NyaXB0Pg">';
        ob_start();
        $handler->sendPhpResponse(new \RuntimeException($htmlWithXss));
        $response = ob_get_clean();

        $this->assertContains(sprintf('<h1 class="break-long-words exception-message">%s</h1>', htmlspecialchars($htmlWithXss, ENT_COMPAT | ENT_SUBSTITUTE, 'UTF-8')), $response);
    }

    public function testStatusCode()
    {
        $handler = new ExceptionHandler(false, 'iso8859-1');

        ob_start();
        $handler->sendPhpResponse(new NotFoundHttpException('Foo'));
        $response = ob_get_clean();

        $this->assertContains('Sorry, the page you are looking for could not be found.', $response);

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
        $response = ob_get_clean();

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
        $exception = new \Exception('foo');

        $handler = $this->getMockBuilder('Symfony\Component\Debug\ExceptionHandler')->setMethods(['sendPhpResponse'])->getMock();
        $handler
            ->expects($this->exactly(2))
            ->method('sendPhpResponse');

        $handler->handle($exception);

        $handler->setHandler(function ($e) use ($exception) {
            $this->assertSame($exception, $e);
        });

        $handler->handle($exception);
    }

    public function testHandleOutOfMemoryException()
    {
        $exception = new OutOfMemoryException('foo', 0, E_ERROR, __FILE__, __LINE__);

        $handler = $this->getMockBuilder('Symfony\Component\Debug\ExceptionHandler')->setMethods(['sendPhpResponse'])->getMock();
        $handler
            ->expects($this->once())
            ->method('sendPhpResponse');

        $handler->setHandler(function ($e) {
            $this->fail('OutOfMemoryException should bypass the handler');
        });

        $handler->handle($exception);
    }
}
