<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Twig\Tests\ErrorRenderer;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\ErrorRenderer\TwigErrorRenderer;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class TwigErrorRendererTest extends TestCase
{
    public function testFallbackToNativeRendererIfDebugOn()
    {
        $exception = new \Exception();

        $twig = $this->createMock(Environment::class);
        $nativeRenderer = $this->createMock(HtmlErrorRenderer::class);
        $nativeRenderer
            ->expects($this->once())
            ->method('render')
            ->with($exception)
        ;

        (new TwigErrorRenderer($twig, $nativeRenderer, true))->render(new \Exception());
    }

    public function testFallbackToNativeRendererIfCustomTemplateNotFound()
    {
        $exception = new NotFoundHttpException();

        $twig = new Environment(new ArrayLoader([]));

        $nativeRenderer = $this->createMock(HtmlErrorRenderer::class);
        $nativeRenderer
            ->expects($this->once())
            ->method('render')
            ->with($exception)
            ->willReturn(FlattenException::createFromThrowable($exception))
        ;

        (new TwigErrorRenderer($twig, $nativeRenderer, false))->render($exception);
    }

    public function testRenderCustomErrorTemplate()
    {
        $twig = new Environment(new ArrayLoader([
            '@Twig/Exception/error404.html.twig' => '<h1>Page Not Found</h1>',
        ]));
        $exception = (new TwigErrorRenderer($twig))->render(new NotFoundHttpException());

        $this->assertSame('<h1>Page Not Found</h1>', $exception->getAsString());
    }
}
