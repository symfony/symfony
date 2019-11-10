<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorHandler\Tests\ErrorRenderer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;
use Symfony\Component\ErrorHandler\Exception\FlattenException;

class HtmlErrorRendererTest extends TestCase
{
    /**
     * @dataProvider getRenderData
     */
    public function testRender(\Throwable $exception, HtmlErrorRenderer $errorRenderer, string $expected)
    {
        $this->assertStringMatchesFormat($expected, $errorRenderer->render($exception)->getAsString());
    }

    public function getRenderData(): iterable
    {
        $expectedDebug = <<<HTML
<!-- Foo (500 Internal Server Error) -->
<!DOCTYPE html>
<html lang="en">
%A<title>Foo (500 Internal Server Error)</title>
%A<div class="trace trace-as-html" id="trace-box-1">%A
<!-- Foo (500 Internal Server Error) -->
HTML;

        $expectedNonDebug = <<<HTML
<!DOCTYPE html>
<html>
%A<title>An Error Occurred: Internal Server Error</title>
%A<h2>The server returned a "500 Internal Server Error".</h2>%A
HTML;

        yield '->render() returns the HTML content WITH stack traces in debug mode' => [
            new \RuntimeException('Foo'),
            new HtmlErrorRenderer(true),
            $expectedDebug,
        ];

        yield '->render() returns the HTML content WITHOUT stack traces in non-debug mode' => [
            new \RuntimeException('Foo'),
            new HtmlErrorRenderer(false),
            $expectedNonDebug,
        ];
    }
}
