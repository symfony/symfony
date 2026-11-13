<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Html;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Html\HtmlBuilder;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Widget\EditorWidget;
use Symfony\Component\Tui\Widget\InputWidget;

final class TextEntryTest extends TestCase
{
    public function testMaxlengthStopsTypingAndCutsPastes()
    {
        $input = $this->input('<input id="i" aria-label="I" maxlength="5" value="ab">');

        $input->handleInput('c');
        $input->handleInput("\x1b[200~defgh\x1b[201~");
        $this->assertSame('abcde', $input->getValue(), 'The paste is cut to fit.');
        $input->handleInput('z');
        $this->assertSame('abcde', $input->getValue());

        $input->setValue('longer than five');
        $this->assertSame('longer than five', $input->getValue(), 'Like browsers, only edits are limited.');
    }

    public function testTextareaPlaceholderReadonlyAndMaxlength()
    {
        $view = (new HtmlBuilder())->build('<textarea id="p" aria-label="P" placeholder="Tell us"></textarea>
            <textarea id="r" aria-label="R" readonly>fixed</textarea>
            <textarea id="m" aria-label="M" maxlength="3">ab</textarea>');
        $placeholder = $view->getById('p');
        $readonly = $view->getById('r');
        $limited = $view->getById('m');
        $this->assertInstanceOf(EditorWidget::class, $placeholder);
        $this->assertInstanceOf(EditorWidget::class, $readonly);
        $this->assertInstanceOf(EditorWidget::class, $limited);

        $lines = array_map(static fn (string $line) => trim(AnsiUtils::stripAnsiCodes($line)), $placeholder->render(new RenderContext(20, 10)));
        $this->assertContains('Tell us', $lines);

        $changes = 0;
        $readonly->onChange(static function () use (&$changes) { ++$changes; });
        $readonly->handleInput('x');
        $readonly->handleInput("\x7f");
        $readonly->handleInput("\x1b[200~pasted\x1b[201~");
        $this->assertSame('fixed', $readonly->getText());
        $this->assertSame(0, $changes, 'No change is announced.');
        $this->assertTrue($readonly->hasState('read-only'));

        // The cursor starts at the beginning of a textarea
        $limited->handleInput('c');
        $limited->handleInput('d');
        $this->assertSame('cab', $limited->getText(), 'The fourth character is refused.');
        $limited->handleInput("\x7f");
        $this->assertSame('ab', $limited->getText(), 'Deleting is always allowed.');
    }

    public function testDatalistSuggestions()
    {
        $view = (new HtmlBuilder())->build('<input id="i" aria-label="Lang" list="langs"><datalist id="langs"><option value="PHP"><option>Python</option><option value="Perl" disabled></datalist>');
        $input = $view->getById('i');
        $this->assertInstanceOf(InputWidget::class, $input);
        $this->assertInstanceOf(\Symfony\Component\Tui\Widget\ComboboxWidget::class, $view->getBody()->all()[0], 'The input and the list of its suggestions; the datalist is not drawn.');
        $this->assertSame($input, $view->getBody()->all()[0]->getInput());

        $input->handleInput('p');
        $this->assertSame('HP', $input->getCompletion(), 'The first one extending the value, ignoring the case.');
        $input->handleInput('y');
        $this->assertSame('thon', $input->getCompletion());
        $this->assertStringContainsString('python', AnsiUtils::stripAnsiCodes($input->render(new RenderContext(20, 1))[0]));

        $input->handleInput("\t");
        $this->assertSame('python', $input->getValue(), 'Tab takes it, keeping what was typed.');
        $this->assertNull($input->getCompletion());
    }

    private function input(string $html): InputWidget
    {
        $input = (new HtmlBuilder())->build($html)->getBody()->all()[0];
        $this->assertInstanceOf(InputWidget::class, $input);

        return $input;
    }
}
