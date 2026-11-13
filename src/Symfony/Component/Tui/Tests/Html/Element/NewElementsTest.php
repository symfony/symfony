<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Html\Element;

use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Html\HtmlBuilder;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Tests\Fixtures\CollectingLogger;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\ButtonWidget;
use Symfony\Component\Tui\Widget\CheckboxWidget;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\InputWidget;
use Symfony\Component\Tui\Widget\ProgressBarWidget;
use Symfony\Component\Tui\Widget\RadioWidget;
use Symfony\Component\Tui\Widget\RichTextWidget;

final class NewElementsTest extends TestCase
{
    public function testButton()
    {
        $view = (new HtmlBuilder())->build('<button id="b">  Save   now </button>');

        $button = $view->getById('b');
        $this->assertInstanceOf(ButtonWidget::class, $button);
        $this->assertSame('Save now', $button->getText());
        $this->assertSame('Save now', $button->getLabel(), 'The text is the accessible name.');
    }

    public function testButtonAriaLabelWins()
    {
        $view = (new HtmlBuilder())->build('<button id="b" aria-label="Close">×</button>');

        $this->assertSame('Close', $view->getById('b')->getLabel());
    }

    public function testCheckbox()
    {
        $view = (new HtmlBuilder())->build('<input type="checkbox" id="c" value="yes" checked aria-label="Agree">');

        $checkbox = $view->getById('c');
        $this->assertInstanceOf(CheckboxWidget::class, $checkbox);
        $this->assertTrue($checkbox->isChecked());
        $this->assertSame('yes', $checkbox->getValue());
        $this->assertSame([$checkbox], $view->querySelectorAll('input[type="checkbox"]:checked'));
    }

    public function testRadiosOfTheSameNameFormAGroup()
    {
        $view = (new HtmlBuilder())->build(<<<'HTML'
            <input type="radio" name="size" value="s" id="s" aria-label="Small">
            <input type="radio" name="size" value="l" id="l" aria-label="Large" checked>
            <input type="radio" name="other" value="x" id="x" aria-label="Other" checked>
            HTML);

        $small = $view->getById('s');
        $this->assertInstanceOf(RadioWidget::class, $small);
        $this->assertSame($small->getGroup(), $view->getById('l')->getGroup());
        $this->assertNotSame($small->getGroup(), $view->getById('x')->getGroup());

        $small->toggle();

        $this->assertFalse($view->getById('l')->isChecked());
        $this->assertTrue($view->getById('x')->isChecked(), 'Another name is another group.');
    }

    public function testRadiosOfDifferentDocumentsAreSeparate()
    {
        $builder = new HtmlBuilder();
        $first = $builder->build('<input type="radio" name="n" id="a" aria-label="a" checked>')->getById('a');
        $second = $builder->build('<input type="radio" name="n" id="b" aria-label="b" checked>')->getById('b');

        $this->assertNotSame($first->getGroup(), $second->getGroup());
        $this->assertTrue($first->isChecked());
    }

    public function testPasswordIsMasked()
    {
        $view = (new HtmlBuilder())->build('<style>input::before { content: attr(value) }</style><input type="password" id="p" aria-label="Password" value="s3cr3t">');
        $input = $view->getById('p');
        $this->assertInstanceOf(InputWidget::class, $input);
        $this->assertTrue($input->isMasked());
        $this->assertSame('s3cr3t', $input->getValue());

        $line = AnsiUtils::stripAnsiCodes($input->render(new RenderContext(20, 1))[0]);
        $this->assertStringContainsString('••••••', $line);
        $this->assertStringNotContainsString('s3cr3t', $line);
        $this->assertArrayNotHasKey('value', $view->getStyleSheet()->getDescription($input)[1] ?? [], 'Never in the stylesheet, where attr(value) could show it.');
    }

    public function testImagesShowTheirTextAlternative()
    {
        $logger = new CollectingLogger();
        $view = (new HtmlBuilder(logger: $logger))->build('<div id="d"><img alt="A cat"><img alt=""><img><svg aria-label="Logo"><circle/></svg><video title="Intro"></video><canvas></canvas><iframe></iframe></div>');

        $texts = array_map(static fn ($w) => $w instanceof RichTextWidget ? $w->getText() : $w::class, $view->getById('d')->all());
        $this->assertSame(['[A cat][Logo][Intro]'], $texts, 'In the line of the text around them, like browsers; nothing without one.');

        $view = (new HtmlBuilder())->build('<p id="p">A <a href="/"><svg><title>home</title><path/></svg> Home</a> <img alt="cat" style="display: block"> <svg aria-hidden="true" aria-label="x"></svg></p>');
        $this->assertSame(['A [home] Home', '[cat]'], array_map(static fn ($w) => $w instanceof RichTextWidget ? $w->getPlainText() : $w->getText(), $view->getById('p')->all()), 'The <title> of an <svg>; a block of its own when displayed as one; nothing when hidden.');

        $view = (new HtmlBuilder())->build("<p id=\"p\"><svg><title>a\e]0;title\x07b</title></svg></p>");
        $this->assertSame('[a]0;titleb]', $view->getById('p')->getText(), 'Data: no control bytes.');
        $this->assertSame([], array_filter($logger->messages, static fn (string $m) => str_contains($m, 'not supported')), 'Embedded content is not reported as unsupported.');
    }

    #[RequiresPhpExtension('gd')]
    public function testThePosterOfAVideoAndTheImagesOfObjectsAreDrawn()
    {
        $image = imagecreatetruecolor(16, 16);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 0, 0) ?: 0);
        ob_start();
        imagepng($image);
        $png = 'data:image/png;base64,'.base64_encode((string) ob_get_clean());

        $screen = $this->render('<video poster="'.$png.'" title="Intro">Your browser cannot play it</video><video title="No poster">Fallback</video><video poster="missing.png" title="Broken"></video>', 20);
        $this->assertSame("▀▀\nFallback\n[Broken]", trim($screen), 'Its poster over its fallback content, like a browser that plays videos; else as before.');

        $screen = $this->render('<object data="'.$png.'" type="image/png">Fallback</object><object data="'.$png.'">Untyped</object><object data="report.pdf" type="application/pdf">PDF fallback</object><object data="'.$png.'" type="text/html">HTML</object><embed src="'.$png.'" title="Embedded">', 20);
        $this->assertSame("▀▀\n▀▀\nPDF fallback\nHTML\n▀▀", trim($screen), 'The image an <object> or an <embed> holds, unless its type is another.');
    }

    public function testTable()
    {
        $html = <<<'HTML'
            <table>
              <caption>Scores</caption>
              <thead><tr><th>Name</th><th>Score</th></tr></thead>
              <tbody><tr><td>Ada</td><td>10</td></tr><tr><td colspan="2" id="wide">wide</td><td>x</td></tr></tbody>
            </table>
            HTML;
        $view = (new HtmlBuilder())->build($html);

        $this->assertSame(2, $view->getById('wide')->getStyle()?->getColumnSpan());

        $screen = $this->render($html, 40);
        $this->assertStringContainsString('Scores', $screen);
        // The columns are as wide as their widest cell, and line up across the rows
        $this->assertMatchesRegularExpression('/^Name Score$/m', $screen);
        $this->assertMatchesRegularExpression('/^Ada  10$/m', $screen);
        $this->assertMatchesRegularExpression('/^wide       x$/m', $screen, 'A cell spanning two columns takes both, and the gap between them.');
    }

    public function testMeterRegions()
    {
        $region = static function (string $attributes): ?string {
            $view = (new HtmlBuilder())->build('<meter id="m" '.$attributes.'></meter>');

            return array_values(array_intersect(['optimum', 'suboptimum', 'even-less-good'], $view->getById('m')->getStateFlags()))[0] ?? null;
        };

        // The optimum between low and high: in range is optimal
        $this->assertSame('optimum', $region('value="0.5" low="0.3" high="0.7"'));
        $this->assertSame('suboptimum', $region('value="0.9" low="0.3" high="0.7"'));
        // The optimum below low: lower is better
        $this->assertSame('optimum', $region('value="0.1" low="0.3" high="0.7" optimum="0"'));
        $this->assertSame('suboptimum', $region('value="0.5" low="0.3" high="0.7" optimum="0"'));
        $this->assertSame('even-less-good', $region('value="0.9" low="0.3" high="0.7" optimum="0"'));
        // The optimum above high: higher is better
        $this->assertSame('optimum', $region('value="0.9" low="0.3" high="0.7" optimum="1"'));
        $this->assertSame('even-less-good', $region('value="0.1" low="0.3" high="0.7" optimum="1"'));
        // No low, high nor optimum: always optimal
        $this->assertSame('optimum', $region('value="0.2"'));
    }

    public function testMeter()
    {
        $view = (new HtmlBuilder())->build('<meter id="m" min="10" max="20" value="15"></meter><meter id="n" value="2"></meter><meter id="o" min="1" max="1"></meter>');

        $this->assertInstanceOf(ProgressBarWidget::class, $view->getById('m'));
        $this->assertSame(50, $view->getById('m')->getProgress());
        $this->assertSame(100, $view->getById('n')->getProgress(), 'Clamped to the range.');
        $this->assertSame(0, $view->getById('o')->getProgress(), 'An empty range is empty.');
    }

    public function testAddressAndHgroupAreContainers()
    {
        $view = (new HtmlBuilder())->build('<address id="a"><p>Street</p></address><hgroup id="h"><h1>T</h1><p>Sub</p></hgroup>');

        $this->assertInstanceOf(ContainerWidget::class, $view->getById('a'));
        $this->assertCount(2, $view->getById('h')->all());
    }

    public function testNewControlsJoinTheFocusCycle()
    {
        $tui = new Tui(terminal: new VirtualTerminal(40, 10));
        $view = (new HtmlBuilder())->build(<<<'HTML'
            <button id="b">Go</button>
            <input type="checkbox" id="c" aria-label="c">
            <input type="radio" id="r" aria-label="r">
            <button id="d" disabled>Off</button>
            HTML);
        $view->mount($tui);

        $this->assertSame([$view->getById('b'), $view->getById('c'), $view->getById('r')], $tui->getFocusManager()->getCycle());
    }

    public function testLabelWrappingABoxPutsTheTextNextToIt()
    {
        $screen = $this->render('<label><input type="checkbox" checked> Remember me</label><label><input type="radio"> Small</label>', 40);

        $this->assertMatchesRegularExpression('/^\[x\] Remember me$/m', $screen);
        $this->assertMatchesRegularExpression('/^\( \) Small$/m', $screen);
    }

    private function render(string $html, int $columns): string
    {
        $terminal = new VirtualTerminal($columns, 20);
        $tui = new Tui(terminal: $terminal);
        (new HtmlBuilder())->build($html)->mount($tui);
        $tui->start();
        $tui->processRender();
        $tui->stop();

        $screen = new ScreenBuffer($columns, 20);
        $screen->write($terminal->getOutput());

        return implode("\n", array_map('rtrim', explode("\n", $screen->getScreen())));
    }
}
