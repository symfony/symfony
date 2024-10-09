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
use Symfony\Component\ErrorHandler\ErrorRenderer\CliErrorRenderer;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

class TwigErrorRendererTest extends TestCase
{
    public function testDontUseNativeRenderInCliContext()
    {
        $exception = new \Exception();

        $twig = $this->createMock(Environment::class);
        $nativeRenderer = $this->createMock(CliErrorRenderer::class);
        $nativeRenderer
            ->expects($this->never())
            ->method('render')
            ->with($exception)
        ;

        (new TwigErrorRenderer($twig, $nativeRenderer, true))->render(new \Exception());
    }

    public function testCliRenderer()
    {
        $exception = new NotFoundHttpException();
        $twig = new Environment(new ArrayLoader([]));

        $exception = (new TwigErrorRenderer($twig, null, false))->render($exception);

        $exceptionHeaders = $exception->getHeaders();
        if (isset($exceptionHeaders['Content-Type'])) {
            $this->assertSame('text/plain', $exceptionHeaders['Content-Type'], 'The exception does not return HTML contents (to prevent potential XSS vulnerabilities)');
        }

        $this->assertStringNotContainsString('<!DOCTYPE html>', $exception->getAsString(), 'The exception does not include elements of the HTML exception page');
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
