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

class HtmlErrorRendererTest extends TestCase
{
    /**
     * @dataProvider getRenderData
     */
    public function testRender(\Throwable $exception, HtmlErrorRenderer $errorRenderer, string $expected)
    {
        $this->assertStringMatchesFormat($expected, $errorRenderer->render($exception)->getAsString());
    }

    public static function getRenderData(): iterable
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
<html lang="en">
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

    /**
     * @dataProvider provideFileLinkFormats
     */
    public function testFileLinkFormat(\ErrorException $exception, string $fileLinkFormat, bool $withSymfonyIde, string $expected)
    {
        if ($withSymfonyIde) {
            $_ENV['SYMFONY_IDE'] = $fileLinkFormat;
        }
        $errorRenderer = new HtmlErrorRenderer(true, null, $withSymfonyIde ? null : $fileLinkFormat);

        $this->assertStringContainsString($expected, $errorRenderer->render($exception)->getAsString());
    }

    public static function provideFileLinkFormats(): iterable
    {
        $exception = new \ErrorException('Notice', 0, \E_USER_NOTICE);

        yield 'file link format set as known IDE with SYMFONY_IDE' => [
            $exception,
            'vscode',
            true,
            'href="vscode://file/'.__DIR__,
        ];
        yield 'file link format set as a raw format with SYMFONY_IDE' => [
            $exception,
            'phpstorm://open?file=%f&line=%l',
            true,
            'href="phpstorm://open?file='.__DIR__,
        ];
        yield 'file link format set as known IDE without SYMFONY_IDE' => [
            $exception,
            'vscode',
            false,
            'href="vscode://file/'.__DIR__,
        ];
        yield 'file link format set as a raw format without SYMFONY_IDE' => [
            $exception,
            'phpstorm://open?file=%f&line=%l',
            false,
            'href="phpstorm://open?file='.__DIR__,
        ];
    }

    public function testRendersStackWithoutBinaryStrings()
    {
        // make sure method arguments are available in stack traces (see https://www.php.net/manual/en/ini.core.php)
        ini_set('zend.exception_ignore_args', false);

        $binaryData = file_get_contents(__DIR__.'/../Fixtures/pixel.png');
        $exception = $this->getRuntimeException($binaryData);

        $rendered = (new HtmlErrorRenderer(true))->render($exception)->getAsString();

        $this->assertStringContainsString(
            "buildRuntimeException('FooException')",
            $rendered,
            '->render() contains the method call with "FooException"'
        );

        $this->assertStringContainsString(
            'getRuntimeException(binary string)',
            $rendered,
            '->render() contains the method call with "binary string" replacement'
        );

        $this->assertStringContainsString(
            '<em>binary string</em>',
            $rendered,
            '->render() returns the HTML content with "binary string" replacement'
        );
    }

    private function getRuntimeException(string $unusedArgument): \RuntimeException
    {
        return $this->buildRuntimeException('FooException');
    }

    private function buildRuntimeException(string $message): \RuntimeException
    {
        return new \RuntimeException($message);
    }
}
