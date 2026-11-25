<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Widget;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Widget\DocumentWidget;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\Hyphenation\HyphenatorInterface;
use Symfony\Component\HtmlRenderer\RenderingContext;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Render\RenderContext;

final class DocumentHyphenationTest extends TestCase
{
    public function testWideAndCombiningCharactersKeepSourceOffsetsAroundHiddenHyphens()
    {
        $session = (new HtmlRenderer())->createSession("<input style=\"width:120px;padding:0;border:0;font-size:16px\" value=\"界e\u{0301}&shy;界\">", new RenderingContext(160, 64));
        $widget = new DocumentWidget($session);
        $widget->render(new RenderContext(20, 4));
        $input = $session->getDocument()->querySelector('input');
        $session->focus($input);
        $widget->handleInput("\x1b[F");
        $result = $session->render();
        $carets = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $operation): bool => 'caret' === $operation['type']));
        self::assertSame([4, 4], $session->getTextSelection($input));
        self::assertCount(1, $carets);
        $text = array_values(array_filter($result->getDisplayList()->getOperations(), static fn (array $operation): bool => 'text' === $operation['type'] && "界e\u{0301}界" === $operation['text']));
        self::assertCount(1, $text);
        self::assertSame(40.0, $text[0]['width']);
        self::assertSame($text[0]['x'] + 40.0, $carets[0]['x']);
        self::assertSame(4, $result->getTextPosition($input, $text[0]['x'] + 40, 8));
        self::assertSame(3, $result->getTextPosition($input, $text[0]['x'] + 24, 8));
    }

    public function testTerminalContextAdaptationRetainsTheInjectedDictionary()
    {
        $dictionary = new class implements HyphenatorInterface {
            public function hyphenate(string $word, string $language): array
            {
                return [2];
            }
        };
        $context = new RenderingContext(160, 64, null, null, null, null, [], '', [], $dictionary);
        $session = (new HtmlRenderer())->createSession('<p lang="en" style="width:24px;margin:0;font-size:16px;line-height:16px;hyphens:auto">abcd</p>', $context);
        $lines = (new DocumentWidget($session))->render(new RenderContext(20, 4));
        self::assertSame($dictionary, $session->getContext()->hyphenator);
        self::assertSame('ab-', rtrim(AnsiUtils::stripAnsiCodes($lines[0])));
        self::assertSame('cd', rtrim(AnsiUtils::stripAnsiCodes($lines[1])));
    }
}
