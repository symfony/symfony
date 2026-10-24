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

use PHPUnit\Framework\Attributes\BackupGlobals;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;
use Symfony\Component\ErrorHandler\Exception\FlattenException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;

class HtmlErrorRendererTest extends TestCase
{
    #[DataProvider('getRenderData')]
    public function testRender(\Throwable $exception, HtmlErrorRenderer $errorRenderer, string $expected)
    {
        $this->assertStringMatchesFormat($expected, $errorRenderer->render($exception)->getAsString());
    }

    #[BackupGlobals(true)]
    #[TestWith([null, 'composer'])]
    #[TestWith(['', 'composer'])]
    #[TestWith(['symfony', 'symfony composer'])]
    #[TestWith(['symfony-local', 'symfony-local composer'])]
    #[TestWith(['symfony<&>"', 'symfony<&>" composer'])]
    #[TestWith(['0', '0 composer'])]
    public function testComposerHintsUseSymfonyCli(?string $binaryName, string $prefix)
    {
        if (null === $binaryName) {
            unset($_SERVER['SYMFONY_CLI_BINARY_NAME']);
        } else {
            $_SERVER['SYMFONY_CLI_BINARY_NAME'] = $binaryName;
        }

        $message = 'Try running "composer require acme/<package>". Already suggested "symfony composer require acme/ready".';
        $previousMessage = 'Try running "composer require acme/previous".';
        $previous = new \Error($previousMessage);
        $exception = new \RuntimeException($message, 0, $previous);
        $renderer = new HtmlErrorRenderer(true);
        $rendered = $renderer->render($exception);
        $html = $rendered->getAsString();
        $prefix = htmlspecialchars($prefix, \ENT_COMPAT);

        $this->assertStringContainsString('class="exc-item-message">Try running &quot;'.$prefix.' require acme/&lt;package&gt;&quot;.', $html);
        $this->assertStringContainsString('class="exc-item-message">Try running &quot;'.$prefix.' require acme/previous&quot;.', $html);
        $this->assertStringContainsString('&quot;symfony composer require acme/ready&quot;', $html);
        $this->assertStringNotContainsString('symfony symfony composer', $html);
        $this->assertSame($message, $exception->getMessage());
        $this->assertSame($previousMessage, $previous->getMessage());
        $this->assertSame($previousMessage, $rendered->getPrevious()->getMessage());
        $this->assertSame($message, $rendered->getMessage());
        $this->assertSame(rawurlencode($message), $rendered->getHeaders()['X-Debug-Exception']);

        $flattened = FlattenException::createFromThrowable($exception);
        $body = $renderer->getBody($flattened);
        $this->assertStringContainsString('&quot;'.$prefix.' require acme/&lt;package&gt;&quot;', $body);
        $this->assertStringContainsString('&quot;'.$prefix.' require acme/previous&quot;', $body);
        $this->assertSame($message, $flattened->getMessage());
        $this->assertSame($previousMessage, $flattened->getPrevious()->getMessage());

        $single = $renderer->getBody(FlattenException::createFromThrowable(new \RuntimeException($message)));
        $this->assertStringContainsString('&quot;'.$prefix.' require acme/&lt;package&gt;&quot;', $single);
    }

    #[BackupGlobals(true)]
    public function testComposerHintsDoNotChangeLogsSourceOrTraceArguments()
    {
        $_SERVER['SYMFONY_CLI_BINARY_NAME'] = 'symfony';
        $ignoreArgs = ini_set('zend.exception_ignore_args', '0');

        try {
            $line = __LINE__ + 1;
            $source = '"composer require acme/source"';
            $exception = (static fn (string $argument) => new \ErrorException('Try running "composer require acme/message".', 0, \E_ERROR, __FILE__, $line))('"composer require acme/argument"');
            $logger = $this->createDebugLogger('Try running "composer require acme/log".');
            $html = (new HtmlErrorRenderer(true, null, null, null, '', $logger))->render($exception)->getAsString();

            $this->assertStringContainsString('Try running &quot;symfony composer require acme/message&quot;.', $html);
            $this->assertStringContainsString('class="log-message break-long-words" id="log-0-message">Try running &quot;composer require acme/log&quot;.', $html);
            $this->assertStringContainsString('<span class="trace-code-string">'.var_export($source, true).'</span>', $html);
            $this->assertStringContainsString('"composer require acme/argument"', html_entity_decode(strip_tags($html)));
        } finally {
            ini_set('zend.exception_ignore_args', $ignoreArgs);
        }
    }

    #[DataProvider('provideNonceDebugModes')]
    public function testNoncesApplyOnlyToRendererAssets(bool $debug)
    {
        $buffer = '<script id="untrusted">alert(1)</script><style id="untrusted">body {color: red}</style>';
        $renderer = new HtmlErrorRenderer($debug, null, null, null, $buffer);
        $renderer->setNonce('script"&', 'style"&');
        $exception = new NonceTestException('Failure');

        $html = $renderer->render($exception)->getAsString();
        preg_match_all('/<(script|style)\b[^>]*>/', $html, $tags, \PREG_SET_ORDER);

        self::assertNotEmpty($tags);
        foreach ($tags as $tag) {
            if (str_contains($tag[0], 'id="untrusted"')) {
                self::assertStringNotContainsString('nonce=', $tag[0]);
            } else {
                self::assertStringContainsString('nonce="'.$tag[1].'&quot;&amp;"', $tag[0]);
            }
        }
        if ($debug) {
            self::assertStringContainsString('Sfdump(', $html);
            self::assertStringContainsString($buffer, $html);
        }
    }

    public static function provideNonceDebugModes(): iterable
    {
        yield [false];
        yield [true];
    }

    public function testNonceProviderIsResolvedOncePerRender()
    {
        $generation = 0;
        $renderer = new HtmlErrorRenderer(true);
        $renderer->setNonce(static function () use (&$generation): string { return 'nonce-'.++$generation; });

        $first = $renderer->render(new \RuntimeException())->getAsString();
        self::assertSame(1, $generation);
        self::assertStringContainsString('<script nonce="nonce-1">', $first);
        self::assertStringContainsString('<style nonce="nonce-1">', $first);

        $second = $renderer->render(new \RuntimeException())->getAsString();
        self::assertSame(2, $generation);
        self::assertStringContainsString('<script nonce="nonce-2">', $second);
        self::assertStringNotContainsString('<script nonce="nonce-1">', $second);

        $renderer->setNonce(null);
        self::assertStringNotContainsString('<script nonce=', $renderer->render(new \RuntimeException())->getAsString());
    }

    #[DataProvider('provideHighlightColors')]
    public function testCodeExcerptsDoNotUseInlineStyles(array $colors)
    {
        $previousColors = [];
        foreach ($colors as $name => $color) {
            $previousColors[$name] = ini_set($name, $color);
        }

        try {
            $renderer = new HtmlErrorRenderer(true);
            $exception = new \ErrorException('Failure', 0, \E_ERROR, __DIR__.'/../Fixtures/highlight.php', 6);
            $html = $renderer->render($exception)->getAsString();

            self::assertDoesNotMatchRegularExpression('/<[^>]+\sstyle=/', $html);
            self::assertStringContainsString('data-ln-chars="2"', $html);
            foreach (['comment', 'default', 'keyword', 'string'] as $token) {
                self::assertStringContainsString('class="trace-code-'.$token.'"', $html);
            }
        } finally {
            foreach ($previousColors as $name => $color) {
                ini_set($name, $color);
            }
        }
    }

    public static function provideHighlightColors(): iterable
    {
        yield 'default colors' => [[]];
        yield 'custom colors' => [[
            'highlight.comment' => '#123456',
            'highlight.default' => '#234567',
            'highlight.html' => '#345678',
            'highlight.keyword' => '#456789',
            'highlight.string' => '#56789a',
        ]];
    }

    public function testCodeExcerptsKeepSourceColorsAsText()
    {
        $renderer = new HtmlErrorRenderer(true);
        $exception = new \ErrorException('Failure', 0, \E_ERROR, __DIR__.'/../Fixtures/highlight.php', 6);

        self::assertStringContainsString("'#FF8000'", $renderer->render($exception)->getAsString());
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

    #[DataProvider('provideFileLinkFormats')]
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
        // make sure method arguments are available in stack traces (see https://php.net/ini.core)
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
            '<span class="trace-arg-object">binary string</span>',
            $rendered,
            '->render() returns the HTML content with "binary string" replacement'
        );
    }

    public function testTheCopyStatusRegionIsPartOfTheRenderedBody()
    {
        // the profiler embeds this fragment and scopes the stylesheet to it, so the region
        // cannot be appended to the document from JavaScript
        $body = (new HtmlErrorRenderer(true))->getBody(FlattenException::createFromThrowable(new \RuntimeException('Foo')));

        $this->assertStringContainsString('<p class="sr-only" role="status" data-announcer></p>', $body);
    }

    public function testExpandableLogRowsNameThemselvesFromTheirParts()
    {
        $rendered = (new HtmlErrorRenderer(true, null, null, null, '', $this->createDebugLogger()))->render(new \RuntimeException('Foo'))->getAsString();

        $this->assertStringContainsString('aria-labelledby="log-0-time log-0-level log-0-channel log-0-message"', $rendered);
        $this->assertStringContainsString('<span class="log-time" id="log-0-time">', $rendered);
        $this->assertStringContainsString('<span class="log-message break-long-words" id="log-0-message">', $rendered);

        // a row without context is not expandable, so it has no summary to name
        $this->assertStringNotContainsString('id="log-1-', $rendered);
    }

    private function createDebugLogger(string $message = 'With context'): LoggerInterface&DebugLoggerInterface
    {
        return new class($message) extends AbstractLogger implements DebugLoggerInterface {
            public function __construct(private string $message)
            {
            }

            public function log($level, $message, array $context = []): void
            {
            }

            public function getLogs(?Request $request = null): array
            {
                return [
                    ['timestamp' => 0, 'timestamp_rfc3339' => '1970-01-01T00:00:00.000+00:00', 'message' => $this->message, 'priority' => 200, 'priorityName' => 'INFO', 'channel' => 'request', 'context' => ['foo' => 'bar']],
                    ['timestamp' => 0, 'timestamp_rfc3339' => '1970-01-01T00:00:00.000+00:00', 'message' => 'Without context', 'priority' => 200, 'priorityName' => 'INFO', 'channel' => 'request', 'context' => []],
                ];
            }

            public function countErrors(?Request $request = null): int
            {
                return 0;
            }

            public function clear(): void
            {
            }
        };
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

class NonceTestException extends \RuntimeException
{
    public array $details = ['nested' => ['value']];
}
