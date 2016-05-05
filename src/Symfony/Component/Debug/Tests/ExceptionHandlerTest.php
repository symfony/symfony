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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

require_once __DIR__.'/HeaderMock.php';

class ExceptionHandlerTest extends \PHPUnit_Framework_TestCase
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

        $this->assertContains('<h1>Whoops, looks like something went wrong.</h1>', $response);
        $this->assertNotContains('<h2 class="block_exception clear_fix">', $response);

        $handler = new ExceptionHandler(true);

        ob_start();
        $handler->sendPhpResponse(new \RuntimeException('Foo'));
        $response = ob_get_clean();

        $this->assertContains('<h1>Whoops, looks like something went wrong.</h1>', $response);
        $this->assertContains('<h2 class="block_exception clear_fix">', $response);
    }

    public function testStatusCode()
    {
        $handler = new ExceptionHandler(false, 'iso8859-1');

        ob_start();
        $handler->sendPhpResponse(new NotFoundHttpException('Foo'));
        $response = ob_get_clean();

        $this->assertContains('Sorry, the page you are looking for could not be found.', $response);

        $expectedHeaders = array(
            array('HTTP/1.0 404', true, null),
            array('Content-Type: text/html; charset=iso8859-1', true, null),
        );

        $this->assertSame($expectedHeaders, testHeader());
    }

    public function testHeaders()
    {
        $handler = new ExceptionHandler(false, 'iso8859-1');

        ob_start();
        $handler->sendPhpResponse(new MethodNotAllowedHttpException(array('POST')));
        $response = ob_get_clean();

        $expectedHeaders = array(
            array('HTTP/1.0 405', true, null),
            array('Allow: POST', false, null),
            array('Content-Type: text/html; charset=iso8859-1', true, null),
        );

        $this->assertSame($expectedHeaders, testHeader());

        $handler = new ExceptionHandler(false, 'iso8859-1', null, false);

        ob_start();
        $handler->sendPhpResponse(new MethodNotAllowedHttpException(array('POST')));
        $response = ob_get_clean();

        $expectedHeaders = array(
            array('HTTP/1.0 405', true, null),
            array('Allow: POST', false, null),
            array('Content-Type: text/plain; charset=iso8859-1', true, null),
        );

        $this->assertSame($expectedHeaders, testHeader());
    }

    private function getException($a1, $a2, $a3, $a4, $a5, $a6, $a7)
    {
        return new \Exception('foo');
    }

    public function testTrace()
    {
        $handler = new ExceptionHandler(true);

        $line = __LINE__ + 1;
        $exception = $this->getException(null, 1, 1.0, true, 'foo', array(1, 'b' => 2), new \stdClass());

        $content = $handler->getHtml($exception);

        $this->assertContains('<li>at <abbr title="Symfony\Component\Debug\Tests\ExceptionHandlerTest">ExceptionHandlerTest</abbr>->getException(', $content);
        $this->assertContains("(<em>null</em>, '1', '1', <em>true</em>, 'foo', <em>array</em>('1', 'b' => '2'), <em>object</em>(<abbr title=\"stdClass\">stdClass</abbr>))", $content);
        $this->assertRegExp('@ in <a[^>]+>ExceptionHandlerTest.php line '.$line.'</a></li>\n@', $content);

        $content = $handler->getText($exception);

        $this->assertContains("\n    at ExceptionHandlerTest->getException(", $content);
        $this->assertContains("ExceptionHandlerTest->getException(null, '1', '1', true, 'foo', array('1', 'b' => '2'), object(stdClass))", $content);
        $this->assertContains('in '.__FILE__.':'.$line."\n", $content);

        $this->assertNotRegExp('@^\s*(?<!^|^    )[^\s]@m', $content, 'Lines are indented with 0 or 4 spaces');
        $this->assertNotRegExp('@ $@m', $content, 'Lines have no trailing white-space');
    }

    public function testNestedExceptions()
    {
        $handler = new ExceptionHandler(true);
        ob_start();
        $handler->sendPhpResponse(new \RuntimeException('Foo', 0, new \RuntimeException('Bar')));
        $response = ob_get_clean();

        $this->assertStringMatchesFormat('%A<span class="exception_message">Foo</span>%A<span class="exception_message">Bar</span>%A', $response);
    }

    public function testHandle()
    {
        $exception = new \Exception('foo');

        $handler = $this->getMock('Symfony\Component\Debug\ExceptionHandler', array('sendPhpResponse'));
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

        $handler = $this->getMock('Symfony\Component\Debug\ExceptionHandler', array('sendPhpResponse'));
        $handler
            ->expects($this->once())
            ->method('sendPhpResponse');

        $handler->setHandler(function ($e) {
            $this->fail('OutOfMemoryException should bypass the handler');
        });

        $handler->handle($exception);
    }
}
