<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Html;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\TuiHtml\Html\HtmlBuilder;
use Symfony\Bridge\TuiHtml\Html\HtmlView;
use Symfony\Bridge\TuiHtml\Render\RenderContext;
use Symfony\Bridge\TuiHtml\Widget\ButtonWidget;
use Symfony\Bridge\TuiHtml\Widget\DropdownWidget;
use Symfony\Bridge\TuiHtml\Widget\EditorWidget;
use Symfony\Bridge\TuiHtml\Widget\InputWidget;
use Symfony\Bridge\TuiHtml\Widget\SelectListWidget;
use Symfony\Bridge\TuiHtml\Widget\SliderWidget;
use Symfony\Component\Tui\Ansi\AnsiUtils;

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
        $readonly->onChange(static function () use (&$changes) {
            ++$changes;
        });
        $readonly->handleInput('x');
        $readonly->handleInput('');
        $readonly->handleInput("\x1b[200~pasted\x1b[201~");
        $this->assertSame('fixed', $readonly->getText());
        $this->assertSame(0, $changes, 'No change is announced.');
        $this->assertTrue($readonly->hasState('read-only'));
        // The cursor starts at the beginning of a textarea
        $limited->handleInput('c');
        $limited->handleInput('d');
        $this->assertSame('cab', $limited->getText(), 'The fourth character is refused.');
        $limited->handleInput('');
        $this->assertSame('ab', $limited->getText(), 'Deleting is always allowed.');
    }

    public function testDatalistSuggestions()
    {
        $view = (new HtmlBuilder())->build('<input id="i" aria-label="Lang" list="langs"><datalist id="langs"><option value="PHP"><option>Python</option><option value="Perl" disabled></datalist>');
        $input = $view->getById('i');
        $this->assertInstanceOf(InputWidget::class, $input);
        $this->assertInstanceOf(\Symfony\Bridge\TuiHtml\Widget\ComboboxWidget::class, $view->getBody()->all()[0], 'The input and the list of its suggestions; the datalist is not drawn.');
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

    public function testWhatOtherBackendsNeedToKnowOfTheControls()
    {
        $view = (new HtmlBuilder())->build('<style>.plain { appearance: none }</style>
            <input id="code" aria-label="Code" maxlength="4"> <input id="free" aria-label="Free">
            <input id="file" type="file" aria-label="File"> <input id="lang" aria-label="Lang" list="langs"><datalist id="langs"><option>PHP</option></datalist>
            <textarea id="memo" aria-label="Memo" maxlength="9"></textarea> <textarea id="plain-memo" class="plain" aria-label="Plain"></textarea>
            <button id="go">Go</button> <button id="plain-go" class="plain">Go</button>
            <select id="pick" aria-label="Pick"><option>A</option></select> <select id="plain-pick" class="plain" aria-label="Plain pick"><option>A</option></select>
            <input id="age" type="number" aria-label="Age" min="1" max="9" step="0.5"> <input id="count" type="number" aria-label="Count">
            <input id="level" type="range" aria-label="Level" min="10" max="20" step="2"> <input id="plain-level" type="range" class="plain" aria-label="Plain level">
            <select id="one" size="3" aria-label="One"><option>A</option></select> <select id="many" multiple aria-label="Many"><option>A</option></select>
            <select id="plain-many" multiple class="plain" aria-label="Plain many"><option>A</option></select>')->computeStyles();

        $this->assertSame(4, $this->widget($view, 'code', InputWidget::class)->getMaxLength());
        $this->assertNull($this->widget($view, 'free', InputWidget::class)->getMaxLength());
        $this->assertSame(9, $this->widget($view, 'memo', EditorWidget::class)->getMaxLength());

        $this->assertFalse($this->widget($view, 'free', InputWidget::class)->isCompleting());
        $this->assertTrue($this->widget($view, 'file', InputWidget::class)->isCompleting(), 'A file field completes paths, before it has any');
        $this->assertTrue($this->widget($view, 'lang', InputWidget::class)->isCompleting());

        $this->assertTrue($this->widget($view, 'memo', EditorWidget::class)->hasNativeAppearance());
        $this->assertFalse($this->widget($view, 'plain-memo', EditorWidget::class)->hasNativeAppearance());
        $this->assertTrue($this->widget($view, 'go', ButtonWidget::class)->hasNativeAppearance());
        $this->assertFalse($this->widget($view, 'plain-go', ButtonWidget::class)->hasNativeAppearance());
        $this->assertTrue($this->widget($view, 'pick', DropdownWidget::class)->hasNativeAppearance());
        $this->assertFalse($this->widget($view, 'plain-pick', DropdownWidget::class)->hasNativeAppearance());

        $this->assertSame(['min' => 1.0, 'max' => 9.0, 'step' => 0.5], $this->widget($view, 'age', InputWidget::class)->getNumberRange());
        $this->assertSame(['min' => null, 'max' => null, 'step' => 1.0], $this->widget($view, 'count', InputWidget::class)->getNumberRange());
        $this->assertNull($this->widget($view, 'free', InputWidget::class)->getNumberRange(), 'Not a number field');
        $level = $this->widget($view, 'level', SliderWidget::class);
        $this->assertSame([10.0, 20.0, 2.0], [$level->getMin(), $level->getMax(), $level->getStep()]);
        $this->assertTrue($level->hasNativeAppearance());
        $this->assertFalse($this->widget($view, 'plain-level', SliderWidget::class)->hasNativeAppearance());
        $this->assertFalse($this->widget($view, 'one', SelectListWidget::class)->isMultiselect());
        $this->assertTrue($this->widget($view, 'many', SelectListWidget::class)->isMultiselect());
        $this->assertTrue($this->widget($view, 'many', SelectListWidget::class)->hasNativeAppearance());
        $this->assertFalse($this->widget($view, 'plain-many', SelectListWidget::class)->hasNativeAppearance());
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function widget(HtmlView $view, string $id, string $class): object
    {
        $widget = $view->getById($id);
        $this->assertInstanceOf($class, $widget);

        return $widget;
    }

    private function input(string $html): InputWidget
    {
        $input = (new HtmlBuilder())->build($html)->getBody()->all()[0];
        $this->assertInstanceOf(InputWidget::class, $input);

        return $input;
    }
}
