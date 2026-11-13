<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Tests\Html;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Render\RenderContext;
use Symfony\Component\TuiHtml\Widget\EditorWidget;

final class TextareaWrapTest extends TestCase
{
    private const TEXT = 'one two three four';

    public function testTheLinesWrap()
    {
        $this->assertSame(['one two', 'three four'], $this->rows('', '<textarea id="t" aria-label="t">'.self::TEXT.'</textarea>'));
        // white-space inherits, a textarea wraps all the same, like HTML
        $this->assertSame(['one two', 'three four'], $this->rows('div { white-space: nowrap }', '<div><textarea id="t" aria-label="t">'.self::TEXT.'</textarea></div>'));
    }

    public function testWrapOffKeepsEachLineOnOneRow()
    {
        $this->assertSame(['one two th', 'second'], $this->rows('', '<textarea id="t" wrap="off" aria-label="t">'.self::TEXT."\nsecond</textarea>"));
        $this->assertSame(['one two th'], $this->rows('', '<textarea id="t" wrap="OFF" aria-label="t">'.self::TEXT.'</textarea>'), 'Case-insensitive.');
    }

    public function testWhiteSpaceKeepsTheLinesWhole()
    {
        $this->assertSame(['one two th'], $this->rows('textarea { white-space: pre }', '<textarea id="t" aria-label="t">'.self::TEXT.'</textarea>'));
        $this->assertSame(['one two', 'three four'], $this->rows('textarea { white-space: pre-wrap }', '<textarea id="t" wrap="off" aria-label="t">'.self::TEXT.'</textarea>'), 'A rule wins.');
    }

    public function testTheLinesScrollSidewaysToTheCursor()
    {
        $editor = new EditorWidget();
        $editor->setLineWrapping(false);
        $editor->setText("abcdefghijklmno\nxy");
        $editor->setFocused(true);
        // The cursor at the end of the last line: in view
        $this->assertSame(['abcdefghij', 'xy'], $this->contentRows($editor));
        // Up, then to the end of the first line: scrolled as little as it takes, a column kept for the cursor
        $editor->handleInput("\x1b[A");
        $editor->handleInput("\x05");
        $this->assertSame(['ghijklmno', ''], $this->contentRows($editor));
        // Back to the start: scrolled back
        $editor->handleInput("\x01");
        $this->assertSame(['abcdefghij', 'xy'], $this->contentRows($editor));
    }

    public function testAWideCharacterCutOnTheLeftIsASpace()
    {
        $editor = new EditorWidget();
        $editor->setLineWrapping(false);
        $editor->setText('a日本語のテキスト');
        $editor->handleInput("\x05");
        // Scrolled 8 columns: の is over the 8th and 9th
        $this->assertSame(' テキスト', $this->contentRows($editor)[0]);
    }

    private function rows(string $css, string $html): array
    {
        $view = (new HtmlBuilder())->build('<style>'.$css.'</style>'.$html)->computeStyles();
        $editor = $view->getById('t');
        $this->assertInstanceOf(EditorWidget::class, $editor);

        return array_values(array_filter($this->contentRows($editor), static fn (string $row) => '' !== $row));
    }

    private function contentRows(EditorWidget $editor): array
    {
        $lines = $editor->render(new RenderContext(10, 10));

        return array_map(static fn (string $line) => rtrim(AnsiUtils::stripAnsiCodes($line)), \array_slice($lines, 1, -1));
    }
}
