<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\Tests\Controller;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Controller\ErrorController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class ErrorControllerTest extends TestCase
{
    /**
     * @dataProvider getInvokeControllerDataProvider
     */
    public function testInvokeController(Request $request, \Exception $exception, int $statusCode, string $content)
    {
        $kernel = $this->getMockBuilder(HttpKernelInterface::class)->getMock();
        $errorRenderer = new HtmlErrorRenderer();
        $controller = new ErrorController($kernel, null, $errorRenderer);
        $response = $controller($exception);

        $this->assertSame($statusCode, $response->getStatusCode());
        self::assertStringContainsString($content, strtr($response->getContent(), ["\n" => '', '    ' => '']));
    }

    public function getInvokeControllerDataProvider()
    {
        yield 'default status code and HTML format' => [
            new Request(),
            new \Exception(),
            500,
            'The server returned a "500 Internal Server Error".',
        ];

        yield 'custom status code' => [
            new Request(),
            new NotFoundHttpException('Page not found.'),
            404,
            'The server returned a "404 Not Found".',
        ];

        $request = new Request();
        $request->attributes->set('_format', 'unknown');
        yield 'default HTML format for unknown formats' => [
            $request,
            new HttpException(405, 'Invalid request.'),
            405,
            'The server returned a "405 Method Not Allowed".',
        ];
    }

    public function testPreviewController()
    {
        $_controller = 'error_controller';
        $code = 404;

        $kernel = $this->getMockBuilder(HttpKernelInterface::class)->getMock();
        $kernel
            ->expects($this->once())
            ->method('handle')
            ->with(
                $this->callback(function (Request $request) use ($_controller, $code) {
                    $exception = $request->attributes->get('exception');

                    $this->assertSame($_controller, $request->attributes->get('_controller'));
                    $this->assertInstanceOf(\Throwable::class, $exception);
                    $this->assertSame($code, $exception->getStatusCode());
                    $this->assertFalse($request->attributes->get('showException'));

                    return true;
                }),
                $this->equalTo(HttpKernelInterface::SUB_REQUEST)
            )
            ->willReturn($response = new Response());

        $controller = new ErrorController($kernel, $_controller, new HtmlErrorRenderer());

        $this->assertSame($response, $controller->preview(new Request(), $code));
    }
}
