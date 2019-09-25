<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ErrorRenderer\Tests\ErrorRenderer;

use PHPUnit\Framework\TestCase;
use Symfony\Component\ErrorRenderer\ErrorRenderer\ErrorRendererInterface;
use Symfony\Component\ErrorRenderer\ErrorRenderer\HtmlErrorRenderer;
use Symfony\Component\ErrorRenderer\Exception\FlattenException;

class HtmlErrorRendererTest extends TestCase
{
    /**
     * @dataProvider getRenderData
     */
    public function testRender(FlattenException $exception, ErrorRendererInterface $errorRenderer, string $expected)
    {
        $this->assertStringMatchesFormat($expected, $errorRenderer->render($exception));
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
            FlattenException::createFromThrowable(new \RuntimeException('Foo')),
            new HtmlErrorRenderer(true),
            $expectedDebug,
        ];

        yield '->render() returns the HTML content WITHOUT stack traces in non-debug mode' => [
            FlattenException::createFromThrowable(new \RuntimeException('Foo')),
            new HtmlErrorRenderer(false),
            $expectedNonDebug,
        ];

        yield '->render() returns the HTML content WITHOUT stack traces in debug mode FORCING non-debug via X-Debug header' => [
            FlattenException::createFromThrowable(new \RuntimeException('Foo'), null, ['X-Debug' => false]),
            new HtmlErrorRenderer(true),
            $expectedNonDebug,
        ];

        yield '->render() returns the HTML content WITHOUT stack traces in non-debug mode EVEN FORCING debug via X-Debug header' => [
            FlattenException::createFromThrowable(new \RuntimeException('Foo'), null, ['X-Debug' => true]),
            new HtmlErrorRenderer(false),
            $expectedNonDebug,
        ];
    }
}
