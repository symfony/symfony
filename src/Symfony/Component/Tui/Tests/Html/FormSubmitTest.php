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

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Event\FormSubmitEvent;
use Symfony\Component\Tui\Html\HtmlBuilder;
use Symfony\Component\Tui\Html\HtmlView;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ButtonWidget;
use Symfony\Component\Tui\Widget\FormWidget;
use Symfony\Component\Tui\Widget\InputWidget;

final class FormSubmitTest extends TestCase
{
    private const FORM = <<<'HTML'
        <form id="f">
          <input name="name" aria-label="Name" value="Ada">
          <input name="email" type="email" aria-label="Email" value="ada@example.com" required>
          <input type="checkbox" name="tags[]" value="php" aria-label="PHP" checked>
          <input type="checkbox" name="tags[]" value="go" aria-label="Go">
          <input type="checkbox" name="tags[]" value="rust" aria-label="Rust" checked>
          <input type="radio" name="plan" value="free" aria-label="Free">
          <input type="radio" name="plan" value="pro" aria-label="Pro" checked>
          <select name="lang" aria-label="Lang"><option value="en">English</option><option value="fr" selected>French</option></select>
          <textarea name="bio" aria-label="Bio">Engines</textarea>
          <input name="off" aria-label="Off" value="x" disabled>
          <input aria-label="No name" value="y">
          <button type="button" id="other">Other</button>
          <button name="action" value="save" id="save">Save</button>
          <input type="submit" name="action" value="publish" id="publish">
        </form>
        HTML;

    public function testASubmitButtonSubmitsTheNamedEnabledValues()
    {
        [$view, $events] = $this->build(self::FORM);

        $this->press($view, 'save');

        $this->assertCount(1, $events);
        $this->assertSame([
            'name' => 'Ada',
            'email' => 'ada@example.com',
            'tags' => ['php', 'rust'],
            'plan' => 'pro',
            'lang' => 'fr',
            'bio' => 'Engines',
            'action' => 'save',
        ], $events[0]->getValues(), 'Only the button that submitted the form counts.');
        $this->assertSame($view->getById('save'), $events[0]->getSubmitter());

        $this->press($view, 'publish');
        $this->assertSame('publish', $events[1]->getValues()['action']);

        $this->press($view, 'other');
        $this->assertCount(2, $events, 'type="button" does not submit.');
    }

    public function testHiddenInputsAndImageButtons()
    {
        [$view, $events] = $this->build('<form id="f"><input type="hidden" name="_token" value="abc"><input name="q" aria-label="Q" value="php"><input type="image" name="go" alt="Search" src="go.png" id="go"></form>');

        $this->assertSame('Search', $view->getById('go')->getLabel(), 'An image button is labeled by its alternative text.');
        $this->press($view, 'go');

        $this->assertSame(['_token' => 'abc', 'q' => 'php', 'go.x' => '0', 'go.y' => '0'], $events[0]->getValues(), 'A hidden value is submitted; an image button sends where it was clicked.');
        $view->computeStyles();
        $this->assertTrue($view->getStyleSheet()->getStyles($view->getById('f')->all()[0])[0]->getHidden(), 'Nothing is drawn for a hidden input.');
    }

    #[RequiresPhpExtension('gd')]
    public function testAnImageButtonDrawsItsImage()
    {
        $image = imagecreatetruecolor(16, 16);
        ob_start();
        imagepng($image);
        $png = 'data:image/png;base64,'.base64_encode((string) ob_get_clean());
        [$view, $events] = $this->build('<form id="f"><input type="image" name="go" alt="Search" src="'.$png.'" id="go"></form>');

        $button = $view->getById('go');
        $this->assertInstanceOf(ButtonWidget::class, $button);
        $this->assertSame('Search', $button->getLabel(), 'Its text alternative is its name.');
        $this->assertSame('[ ▀▀ ]', AnsiUtils::stripAnsiCodes($button->render(new RenderContext(20, 1))[0]), 'Its image drawn, two columns for a square one.');
        $this->press($view, 'go');
        $this->assertSame(['go.x' => '0', 'go.y' => '0'], $events[0]->getValues());
    }

    public function testEnterInAFieldSubmitsThroughTheFirstSubmitButton()
    {
        [$view, $events] = $this->build(self::FORM);
        $name = $view->querySelector('#f input');
        $this->assertInstanceOf(InputWidget::class, $name);

        $name->handleInput("\r");

        $this->assertCount(1, $events);
        $this->assertSame('save', $events[0]->getValues()['action']);
    }

    public function testAnInvalidFormIsNotSubmitted()
    {
        $tui = new Tui(terminal: new VirtualTerminal(60, 30));
        [$view, $events] = $this->build('<form id="f"><input id="a" name="a" aria-label="A" required><input id="b" name="b" aria-label="B" value="ok"><button id="go">Go</button></form>', $tui);

        $this->press($view, 'go');

        $this->assertCount(0, $events);
        $this->assertTrue($view->getById('a')->hasState('user-invalid'));
        $this->assertSame($view->getById('a'), $tui->getFocus(), 'The first invalid control takes the focus.');

        $a = $view->getById('a');
        $this->assertInstanceOf(InputWidget::class, $a);
        $a->setValue('filled');
        $a->handleInput('!');
        $this->press($view, 'go');
        $this->assertCount(1, $events);
    }

    public function testNovalidate()
    {
        [$view, $events] = $this->build('<form novalidate><input name="a" aria-label="A" required><button id="go">Go</button></form>');
        $this->press($view, 'go');
        $this->assertSame(['a' => ''], $events[0]->getValues());

        [$view, $events] = $this->build('<form><input name="a" aria-label="A" required><button id="go" formnovalidate>Go</button></form>');
        $this->press($view, 'go');
        $this->assertCount(1, $events);
    }

    public function testASingleFieldSubmitsImplicitly()
    {
        [$view, $events] = $this->build('<form id="f"><input name="q" aria-label="Search" value="tui"></form>');
        $q = $view->getById('f')->all()[0];
        $this->assertInstanceOf(InputWidget::class, $q);
        $q->handleInput("\r");
        $this->assertSame([['q' => 'tui']], array_map(static fn (FormSubmitEvent $e) => $e->getValues(), $events->getArrayCopy()));
        $this->assertNull($events[0]->getSubmitter());

        [$view, $events] = $this->build('<form id="f"><input name="a" aria-label="A"><input name="b" aria-label="B"></form>');
        $a = $view->querySelector('#f input');
        $this->assertInstanceOf(InputWidget::class, $a);
        $a->handleInput("\r");
        $this->assertCount(0, $events, 'Two fields and no submit button: Enter does not submit.');
    }

    public function testColorAndFileFields()
    {
        [$view, $events] = $this->build('<form id="f"><input type="color" id="c" name="accent" aria-label="Accent" value="#2563EB"><input type="file" id="p" name="avatar" aria-label="Avatar"><button id="go">Go</button></form>');
        $color = $view->getById('c');
        $this->assertInstanceOf(InputWidget::class, $color);
        $this->assertSame('#2563eb', $color->getValue());
        $composite = $color->getParent();
        $this->assertInstanceOf(\Symfony\Component\Tui\Widget\ColorInputWidget::class, $composite);
        $this->assertSame('#2563eb', $composite->getSwatchColor()?->toHex(), 'A swatch of it.');

        $view->getById('p')->setValue('/home/ada/me.png', true);
        $view->getById('go')->activate();
        $this->assertSame(['accent' => '#2563eb', 'avatar' => '/home/ada/me.png'], $events[0]->getValues() ?? null);

        $color->setValue('blue', true);
        $this->assertTrue($color->hasState('invalid'), 'Only #rrggbb.');
        $this->assertNull($composite->getSwatchColor(), 'No color: a dim pattern.');
    }

    public function testAResetButtonRestoresTheInitialValues()
    {
        [$view, $events] = $this->build('<form id="f">
            <input id="name" name="name" aria-label="Name" value="Ada" required>
            <input id="box" type="checkbox" name="b" aria-label="B" checked>
            <select id="lang" name="lang" aria-label="Lang"><option>en</option><option selected>fr</option></select>
            <button type="reset" id="reset">Reset</button>
            <input type="reset" id="reset2">
        </form>');
        $name = $view->getById('name');
        $box = $view->getById('box');
        $dropdown = $view->getById('lang');
        $this->assertInstanceOf(\Symfony\Component\Tui\Widget\DropdownWidget::class, $dropdown, 'A select without size is a dropdown.');
        $lang = $dropdown->getList();
        $this->assertInstanceOf(InputWidget::class, $name);
        $this->assertInstanceOf(\Symfony\Component\Tui\Widget\CheckboxWidget::class, $box);
        $this->assertInstanceOf(\Symfony\Component\Tui\Widget\SelectListWidget::class, $lang);

        $name->setValue('', true);
        $box->toggle();
        $lang->setSelectedIndex(0);
        $this->assertTrue($name->hasState('user-invalid'));

        $this->press($view, 'reset');

        $this->assertSame(['Ada', true, 'fr'], [$name->getValue(), $box->isChecked(), $lang->getSelectedItem()['value'] ?? null]);
        $this->assertTrue($name->hasState('valid'), 'The validity follows the restored value.');
        $this->assertFalse($name->hasState('user-invalid'), 'Nor did the user touch it.');
        $this->assertCount(0, $events, 'A reset does not submit.');

        $name->setValue('Grace', true);
        $this->press($view, 'reset2');
        $this->assertSame('Ada', $name->getValue(), '<input type="reset"> too.');
    }

    public function testArrowsStepANumberField()
    {
        $view = (new HtmlBuilder())->build('<input id="n" type="number" aria-label="N" value="8" min="0" max="10" step="2"><input id="f" type="number" aria-label="F" step="0.5">');
        $n = $view->getById('n');
        $f = $view->getById('f');
        $this->assertInstanceOf(InputWidget::class, $n);
        $this->assertInstanceOf(InputWidget::class, $f);

        $n->handleInput("\x1b[A");
        $this->assertSame('10', $n->getValue());
        $n->handleInput("\x1b[A");
        $this->assertSame('10', $n->getValue(), 'Within max.');
        $n->handleInput("\x1b[B");
        $this->assertSame('8', $n->getValue());
        $n->setValue('3');
        $n->handleInput("\x1b[A");
        $this->assertSame('4', $n->getValue(), 'Snapped to the step from min.');
        $this->assertTrue($n->hasState('user-valid'), 'A step is a change by the user.');

        $f->handleInput("\x1b[A");
        $this->assertSame('0.5', $f->getValue(), 'From empty, the first step.');
        $f->handleInput("\x1b[B");
        $f->handleInput("\x1b[B");
        $this->assertSame('-0.5', $f->getValue());
        $f->handleInput('a');
        $this->assertSame('-0.5a', $f->getValue(), 'Other keys edit as usual.');
    }

    /**
     * @return array{HtmlView, \ArrayObject<int, FormSubmitEvent>}
     */
    public function testControlsOfTheFormAttributeWhereverTheyAre()
    {
        [$view, $events] = $this->build('<input name="before" value="1" form="f"><form id="f"><input name="inside" value="2"><input name="elsewhere" value="x" form="other"><button id="go">Go</button></form><input name="after" value="3" form="f"><button id="outside" form="f" name="b" value="out">Out</button><form id="other"></form>');

        $this->press($view, 'go');
        $this->assertSame(['before' => '1', 'inside' => '2', 'after' => '3'], $events[0]->getValues(), 'Before and after it, not one of another form.');

        $this->press($view, 'outside');
        $this->assertSame('out', $events[1]->getValues()['b'], 'A submit button outside it submits it.');
    }

    public function testActionAndMethod()
    {
        [$view, $events] = $this->build('<form action="/save" method="POST"><input name="a" value="1"><button id="save">Save</button><button id="draft" formaction="/draft" formmethod="get">Draft</button><button id="odd" formmethod="put">Odd</button></form>');

        $this->press($view, 'save');
        $this->assertSame(['/save', 'post'], [$events[0]->getAction(), $events[0]->getMethod()]);
        $this->press($view, 'draft');
        $this->assertSame(['/draft', 'get'], [$events[1]->getAction(), $events[1]->getMethod()], 'Those of the button first.');
        $this->press($view, 'odd');
        $this->assertSame('get', $events[2]->getMethod(), 'An unknown method is get.');
    }

    public function testAFormOfMethodDialogClosesItsDialog()
    {
        $tui = new Tui(terminal: new VirtualTerminal(40, 10));
        [$view, $events] = $this->build('<dialog id="d" open><form method="dialog"><button id="ok" name="answer" value="yes">OK</button></form></dialog>', $tui);
        $dialog = $view->getById('d');
        $this->assertTrue($dialog->hasState('open'));

        $this->press($view, 'ok');

        $this->assertSame(['dialog', ['answer' => 'yes']], [$events[0]->getMethod(), $events[0]->getValues()]);
        $this->assertFalse($dialog->hasState('open'), 'Submitted, the dialog it is in is closed.');
        $this->assertSame('yes', $view->getReturnValue($dialog), 'Its return value is the value of the button.');
    }

    private function build(string $html, ?Tui $tui = null): array
    {
        $view = (new HtmlBuilder())->build($html);
        if (null !== $tui) {
            $view->mount($tui);
            $tui->start();
            $tui->processRender();
        }
        $events = new \ArrayObject();
        $form = $view->querySelector('form');
        $this->assertInstanceOf(FormWidget::class, $form);
        $form->onSubmit(static function (FormSubmitEvent $event) use ($events) { $events[] = $event; });

        return [$view, $events];
    }

    private function press(HtmlView $view, string $id): void
    {
        $button = $view->getById($id);
        $this->assertInstanceOf(ButtonWidget::class, $button);
        $button->activate();
    }
}
