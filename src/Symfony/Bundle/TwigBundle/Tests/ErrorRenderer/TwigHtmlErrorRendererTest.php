<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle\Tests\ErrorRenderer;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\TwigBundle\ErrorRenderer\TwigHtmlErrorRenderer;
use Symfony\Component\ErrorRenderer\ErrorRenderer\HtmlErrorRenderer;
use Symfony\Component\ErrorRenderer\Exception\FlattenException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class TwigHtmlErrorRendererTest extends TestCase
{
    public function testFallbackToNativeRendererIfDebugOn()
    {
        $exception = FlattenException::createFromThrowable(new \Exception());

        $twig = $this->createMock(Environment::class);
        $nativeRenderer = $this->createMock(HtmlErrorRenderer::class);
        $nativeRenderer
            ->expects($this->once())
            ->method('render')
            ->with($exception)
        ;

        (new TwigHtmlErrorRenderer($twig, $nativeRenderer, true))->render($exception);
    }

    public function testFallbackToNativeRendererIfCustomTemplateNotFound()
    {
        $exception = FlattenException::createFromThrowable(new NotFoundHttpException());

        $twig = new Environment(new ArrayLoader([]));

        $nativeRenderer = $this->createMock(HtmlErrorRenderer::class);
        $nativeRenderer
            ->expects($this->once())
            ->method('render')
            ->with($exception)
        ;

        (new TwigHtmlErrorRenderer($twig, $nativeRenderer, false))->render($exception);
    }

    public function testRenderCustomErrorTemplate()
    {
        $exception = FlattenException::createFromThrowable(new NotFoundHttpException());

        $twig = new Environment(new ArrayLoader([
            '@Twig/Exception/error404.html.twig' => '<h1>Page Not Found</h1>',
        ]));

        $nativeRenderer = $this->createMock(HtmlErrorRenderer::class);
        $nativeRenderer
            ->expects($this->never())
            ->method('render')
        ;

        $content = (new TwigHtmlErrorRenderer($twig, $nativeRenderer, false))->render($exception);

        $this->assertSame('<h1>Page Not Found</h1>', $content);
    }
}
