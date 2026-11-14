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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Exception\LogicException;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\MarkdownWidget;
use Symfony\Component\Tui\Widget\TabItem;
use Symfony\Component\Tui\Widget\TabsWidget;
use Symfony\Component\TuiHtml\Css\CssStyleSheet;
use Symfony\Component\TuiHtml\Css\FilesystemStylesheetLoader;
use Symfony\Component\TuiHtml\Html\ElementRegistry;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Html\HtmlView;
use Symfony\Component\TuiHtml\Render\RenderContext;
use Symfony\Component\TuiHtml\Style\Color;
use Symfony\Component\TuiHtml\Style\MediaContext;
use Symfony\Component\TuiHtml\Tests\Fixtures\CollectingLogger;
use Symfony\Component\TuiHtml\Tests\Fixtures\HtmlTui as Tui;
use Symfony\Component\TuiHtml\Widget\CheckboxWidget;
use Symfony\Component\TuiHtml\Widget\CollapsibleWidget;
use Symfony\Component\TuiHtml\Widget\ContainerWidget;
use Symfony\Component\TuiHtml\Widget\DropdownWidget;
use Symfony\Component\TuiHtml\Widget\EditorWidget;
use Symfony\Component\TuiHtml\Widget\HorizontalRuleWidget;
use Symfony\Component\TuiHtml\Widget\InputWidget;
use Symfony\Component\TuiHtml\Widget\LoaderWidget;
use Symfony\Component\TuiHtml\Widget\ProgressBarWidget;
use Symfony\Component\TuiHtml\Widget\RichTextWidget;
use Symfony\Component\TuiHtml\Widget\SelectListWidget;
use Symfony\Component\TuiHtml\Widget\SliderWidget;
use Symfony\Component\TuiHtml\Widget\TextWidget;

final class HtmlBuilderTest extends TestCase
{
    private CollectingLogger $logger;
    private CssStyleSheet $styleSheet;
    private HtmlBuilder $builder;

    protected function setUp(): void
    {
        $this->logger = new CollectingLogger();
        $this->styleSheet = new CssStyleSheet(null, $this->logger);
        $this->builder = new HtmlBuilder($this->styleSheet, null, $this->logger);
    }

    public function testSingleRootElement()
    {
        $root = $this->build('<section id="main"></section>')->getBody()->all()[0];
        $this->assertInstanceOf(ContainerWidget::class, $root);
        $this->assertSame('main', $root->getId());
        $this->assertSame(['section', ['id' => 'main']], $this->styleSheet->getDescription($root));
    }

    public function testNoscriptIsDrawnUntilScriptsRun()
    {
        $html = '<html><body><p id="app">App</p><noscript><p id="fallback">No scripts</p></noscript></body></html>';
        $view = (new HtmlBuilder(new CssStyleSheet()))->build($html);
        $this->assertNotNull($view->getById('fallback'), 'Scripts do not run: drawn.');
        $view = (new HtmlBuilder(new CssStyleSheet()))->setScripting(true)->build($html);
        $this->assertSame([], $view->querySelectorAll('#fallback'), 'Scripts run: left out.');
    }

    public function testDefaultStylesSpaceTheBlocksUnlessLeftOut()
    {
        $render = static function (HtmlBuilder $builder): array {
            $terminal = new VirtualTerminal(20, 6);
            $tui = new Tui(null, $terminal);
            $builder->build('<p>One</p><p>Two</p>')->mount($tui);
            $tui->start();
            $tui->processRender();
            $screen = new ScreenBuffer(20, 6);
            $screen->write($terminal->getOutput());

            return array_map('rtrim', explode("\n", $screen->getScreen()));
        };
        $this->assertSame(['', 'One', '', 'Two'], \array_slice($render(new HtmlBuilder()), 0, 4), 'A line apart, like browsers.');
        $this->assertSame(['One', 'Two'], \array_slice($render((new HtmlBuilder())->setDefaultStyles(false)), 0, 2), 'Left out: an interface laid out by its own rules.');
    }

    public function testDefaultStylesAreSetBeforeTheFirstDocument()
    {
        $builder = new HtmlBuilder();
        $builder->build('<p>a</p>');
        $this->expectException(LogicException::class);
        $builder->setDefaultStyles(false);
    }

    public function testConstraintValidationFollowsTheValue()
    {
        $view = $this->build('<input id="code" aria-label="Code" required pattern="[a-z]+" maxlength="4">
            <input id="age" aria-label="Age" type="number" min="1" max="9" value="12">
            <input id="mail" aria-label="Mails" type="email" multiple value="a@b.c, d@e.f">
            <input id="bad" aria-label="Bad" pattern="(" value="anything">
            <textarea id="bio" aria-label="Bio" minlength="3">ab</textarea>
            <input id="box" type="checkbox" aria-label="Box" required>');
        $states = static fn (string $id) => array_values(array_intersect(['valid', 'invalid', 'user-valid', 'user-invalid', 'in-range', 'out-of-range'], $view->getById($id)->getStateFlags()));
        $this->assertSame(['invalid'], $states('code'));
        $code = $view->getById('code');
        $this->assertInstanceOf(InputWidget::class, $code);
        $code->handleInput('a');
        $this->assertSame(['valid', 'user-valid'], $states('code'));
        $code->handleInput('B');
        $this->assertSame(['invalid', 'user-invalid'], $states('code'), 'The whole value must match the pattern.');
        $code->setValue('abcde');
        $this->assertSame(['invalid', 'user-invalid'], $states('code'), 'setValue() dispatches no change: the states stay.');
        $this->assertSame(['invalid', 'out-of-range'], $states('age'));
        $this->assertSame(['valid'], $states('mail'));
        $this->assertSame(['valid'], $states('bad'), 'An invalid pattern is ignored.');
        $this->assertSame(['invalid'], $states('bio'));
        $box = $view->getById('box');
        $this->assertInstanceOf(CheckboxWidget::class, $box);
        $this->assertSame(['invalid'], $states('box'));
        $box->toggle();
        $this->assertSame(['valid', 'user-valid'], $states('box'));
    }

    public function testDateAndTimeTypesAreValidatedAgainstTheirFormat()
    {
        $view = $this->build('<input id="d" type="date" aria-label="D" value="2026-02-30">
            <input id="e" type="date" aria-label="E" value="2026-11-05" min="2026-01-01" max="2026-06-30">
            <input id="t" type="time" aria-label="T" value="23:59">
            <input id="u" type="time" aria-label="U" value="24:00">
            <input id="l" type="datetime-local" aria-label="L" value="2026-11-05 10:30" max="2026-11-05T11:00">
            <input id="m" type="month" aria-label="M" value="2026-13">
            <input id="w" type="week" aria-label="W" value="2026-W45">
            <input id="r" type="range" aria-label="R" min="0" max="10" value="3" name="r">');
        $states = static fn (string $id) => array_values(array_intersect(['valid', 'invalid', 'in-range', 'out-of-range'], $view->getById($id)->getStateFlags()));
        $this->assertSame(['invalid'], $states('d'), 'February has no 30th.');
        $this->assertSame(['invalid', 'out-of-range'], $states('e'));
        $this->assertSame(['valid'], $states('t'));
        $this->assertSame(['invalid'], $states('u'));
        $this->assertSame(['valid', 'in-range'], $states('l'), 'A space between the date and the time, like a T.');
        $this->assertSame(['invalid'], $states('m'));
        $this->assertSame(['valid'], $states('w'));
        $this->assertInstanceOf(SliderWidget::class, $view->getById('r'));
        $this->assertSame(3.0, $view->getById('r')->getValue());
    }

    public function testFormControlStates()
    {
        $view = $this->build('<form>
            <input id="a" aria-label="A" placeholder="hint" required>
            <input id="b" aria-label="B" readonly value="x">
            <input id="c" aria-label="C" disabled>
            <textarea id="d" aria-label="D"></textarea>
            <select id="e" aria-label="E"><option>o</option></select>
            <input id="f" type="checkbox" aria-label="F" required>
        </form>');
        $states = static fn (string $id) => array_values(array_intersect(['required', 'optional', 'read-write', 'read-only', 'placeholder-shown'], $view->getById($id)->getStateFlags()));
        $this->assertSame(['required', 'read-write', 'placeholder-shown'], $states('a'));
        $this->assertSame(['optional', 'read-only'], $states('b'));
        $this->assertSame(['optional', 'read-only'], $states('c'));
        $this->assertSame(['optional', 'read-write'], $states('d'));
        $this->assertSame(['optional', 'read-only'], $states('e'));
        $this->assertSame(['required', 'read-only'], $states('f'));
        $input = $view->getById('b');
        $this->assertInstanceOf(InputWidget::class, $input);
        $this->assertTrue($input->isReadOnly());
        $this->assertSame('hint', $view->getById('a')->getPlaceholder());
    }

    public function testInlineChildrenOfAFlexContainerAreFlexItems()
    {
        $view = $this->build('<style>nav { display: flex } @media (max-width: 10ch) { .narrow { display: inline-flex } }</style>
            <nav><a href="https://symfony.com" class="home">Home</a> text run <b>bold</b><br></nav>
            <div class="narrow"><i>a</i><i>b</i></div>
            <div style="display: flex"><i>c</i><i>d</i></div>
            <div><i>stays</i> <i>inline</i></div>');
        [$nav, $narrowLine, $styled, $plain] = $view->getBody()->all();
        $this->assertInstanceOf(ContainerWidget::class, $nav);
        // Inline-flex in a media only: lined up with the content around it, in a line of its own where it is a block
        $this->assertSame('tui-line', $this->styleSheet->getDescription($narrowLine)[0]);
        $narrow = $narrowLine->all()[0];
        $items = $nav->all();
        $this->assertCount(3, $items, 'Whitespace and <br> make no item.');
        $this->assertSame(['a', ['href' => 'https://symfony.com']], $this->styleSheet->getDescription($items[0]));
        $this->assertInstanceOf(RichTextWidget::class, $items[0]);
        $this->assertSame('https://symfony.com', $items[0]->getLink());
        $this->assertSame(['home'], $items[0]->getStyleClasses());
        $this->assertSame('tui-text', $this->styleSheet->getDescription($items[1])[0]);
        $this->assertSame('b', $this->styleSheet->getDescription($items[2])[0]);
        $this->assertInstanceOf(ContainerWidget::class, $narrow);
        // Flex in a media only: its items lined up in a row for the others, a row that is no box where it is flex
        $this->assertCount(1, $narrow->all());
        $this->assertSame('tui-line', $this->styleSheet->getDescription($narrow->all()[0])[0]);
        $this->assertCount(2, $narrow->all()[0]->all(), 'Any media counts.');
        $this->assertInstanceOf(ContainerWidget::class, $styled);
        $this->assertCount(2, $styled->all(), 'The style attribute counts.');
        $this->assertInstanceOf(ContainerWidget::class, $plain);
        $this->assertCount(1, $plain->all());
    }

    public function testHtmlAndBodyAreTheRoot()
    {
        $view = $this->build('<p>a</p><p>b</p>');
        $this->assertSame('html', $this->styleSheet->getDescription($view->getRoot())[0]);
        $this->assertSame([$view->getBody()], $view->getRoot()->all());
        $this->assertSame('body', $this->styleSheet->getDescription($view->getBody())[0]);
        $this->assertCount(2, $view->getBody()->all());
    }

    public function testAButtonDrawsWhatIsNotHidden()
    {
        // In its line, as wide as its content
        $button = $this->build('<style>.sr-only { position: absolute; clip: rect(0, 0, 0, 0) }</style><button><span class="sr-only">Close</span> × <span hidden>never</span></button>')->getBody()->all()[0]->all()[0];
        $this->assertSame('Close ×', $button->getLabel(), 'The name keeps what is only visually hidden.');
        $this->assertStringNotContainsString('Close', implode('', $button->render(new RenderContext(20, 1))));
    }

    public function testFocusableContentNamesItself()
    {
        [$details, $links] = $this->build('<details><summary>More <b>info</b></summary><p>a</p></details><p>See <a href="https://symfony.com">the docs</a></p>')->getBody()->all();
        $this->assertSame('More info', $details->getLabel(), 'A <details> is named by its <summary>.');
        $this->assertSame('See the docs', $links->getLabel(), 'Text with links is named by what it says.');
        $this->assertSame([], array_values(array_filter($this->logger->messages, static fn (string $m) => str_contains($m, 'accessible name'))));
    }

    public function testLinksResolveAgainstTheBase()
    {
        $view = $this->build('<!DOCTYPE html><html><head><base href="https://symfony.com/doc/current/"><base href="https://example.com/"></head><body><p><a href="components/tui.html">a</a> <a href="../index.html">b</a> <a href="#top">c</a> <a href="mailto:a@b.c">d</a></p><nav style="display: flex"><a href="/blog">e</a></nav></body></html>');
        [$text, $nav] = $view->getBody()->all();
        $this->assertInstanceOf(RichTextWidget::class, $text);
        $this->assertSame(['https://symfony.com/doc/current/components/tui.html', 'https://symfony.com/doc/index.html', '#top', 'mailto:a@b.c'], $text->getLinks(), 'A link to a fragment stays in the page.');
        $this->assertStringContainsString(';https://symfony.com/doc/current/components/tui.html'."\x07", implode('', $text->render(new RenderContext(80, 1))));
        $this->assertInstanceOf(ContainerWidget::class, $nav);
        $this->assertSame('https://symfony.com/blog', $nav->all()[0]->getLink());
        $this->assertSame(['a', ['href' => '/blog']], $this->styleSheet->getDescription($nav->all()[0]), 'Attribute selectors see the attribute as written.');
    }

    public function testARelativeBaseIsIgnored()
    {
        $text = $this->build('<base href="/doc/"><p><a href="page.html">a</a></p>')->getBody()->all()[0];
        $this->assertSame(['page.html'], $text->getLinks());
        $this->assertNull($text->getBaseUrl());
    }

    public function testHtmlAndBodyKeepTheirAttributes()
    {
        $view = $this->build('<!DOCTYPE html><html lang="fr" class="page"><body dir="rtl"><p>x</p></body></html>');
        $this->assertSame(['lang' => 'fr', 'class' => 'page'], $this->styleSheet->getDescription($view->getRoot())[1]);
        $this->assertSame(['dir' => 'rtl'], $this->styleSheet->getDescription($view->getBody())[1]);
    }

    public function testFullDocument()
    {
        $root = $this->build('<!DOCTYPE html><html><head><title>T</title><style>p { color: red }</style></head><body><p>x</p></body></html>')->getBody()->all()[0];
        $this->assertInstanceOf(TextWidget::class, $root);
        $this->assertSame('x', $root->getText());
    }

    #[DataProvider('provideContainers')]
    public function testContainers(string $tagName)
    {
        $root = $this->build(\sprintf('<%1$s><p>a</p><p>b</p></%1$s>', $tagName))->getBody()->all()[0];
        $this->assertInstanceOf(ContainerWidget::class, $root);
        $this->assertCount(2, $root->all());
        $this->assertSame($tagName, $this->styleSheet->getDescription($root)[0]);
    }

    public static function provideContainers(): iterable
    {
        foreach (['div', 'section', 'main', 'aside', 'header', 'footer', 'nav', 'article', 'form', 'fieldset', 'ul', 'ol', 'tui-column'] as $tagName) {
            yield $tagName => [$tagName];
        }
    }

    public function testColumns()
    {
        $root = $this->build('<tui-columns><tui-column><p>a</p></tui-column></tui-columns>')->getBody()->all()[0];
        $this->assertSame(['columns'], $root->getStyleClasses());
        $this->assertSame(['column'], $root->all()[0]->getStyleClasses());
    }

    public function testTextAndHeadingClasses()
    {
        $root = $this->build('<h1>Title</h1><h6>Small</h6><p>Para</p><span>Span</span><label>Label</label>')->getBody();
        // The span and the label, inline, share a line
        [$h1, $h6, $p, $line] = $root->all();
        [$span, $label] = $line->all();
        $this->assertSame('Title', $h1->getText());
        $this->assertSame(['h1'], $h1->getStyleClasses());
        $this->assertSame(['h6'], $h6->getStyleClasses());
        $this->assertSame(['p'], $p->getStyleClasses());
        $this->assertSame([], $span->getStyleClasses());
        $this->assertInstanceOf(TextWidget::class, $label);
    }

    public function testClassesAreAddedToTheWidget()
    {
        $root = $this->build('<p class=" a  b ">x</p>')->getBody()->all()[0];
        $this->assertSame(['p', 'a', 'b'], $root->getStyleClasses());
        $this->assertArrayNotHasKey('class', $this->styleSheet->getDescription($root)[1]);
    }

    public function testWhitespaceCollapses()
    {
        $this->assertSame('a b c', $this->build("<p>\n  a   b\n\tc  </p>")->getBody()->all()[0]->getText());
    }

    public function testInlineElements()
    {
        $root = $this->build('<p>a <strong>b</strong> <em>c</em> <code>d</code><br>e</p>')->getBody()->all()[0];
        $this->assertSame("a b c d\ne", $root->getText(), 'The look of inline elements comes from CSS.');
        $text = $this->build('<p>a <strong>b</strong> <em>c</em> <code>d</code><br>e</p>')->computeStyles()->getBody()->all()[0]->getText();
        $this->assertStringContainsString(" \x1b[1mb", $text);
        $this->assertStringContainsString(" \x1b[3mc", $text);
        $this->assertStringContainsString(" \x1b[7md", $text);
        $this->assertStringEndsWith("\ne", $text);
    }

    public function testPreservesWhitespaceInPre()
    {
        $this->assertSame("  a\n    b", $this->build("<pre>\n  a\n    b\n</pre>")->computeStyles()->getBody()->all()[0]->getText());
    }

    public function testListMarkers()
    {
        $root = $this->build('<ul><li>a</li><li>b</li></ul><ol start="3"><li>c</li><li>d</li></ol><ol><li>e</li></ol>')->computeStyles()->getBody();
        [$ul, $ol, $plain] = $root->all();
        $text = static fn (RichTextWidget $w) => $w->getMarker().$w->getText();
        $this->assertSame(['• a', '• b'], array_map($text, $ul->all()));
        $this->assertSame(['3. c', '4. d'], array_map($text, $ol->all()));
        $this->assertSame('1. e', $text($plain->all()[0]));
    }

    public function testTextWithBlocksIsAContainer()
    {
        $root = $this->build('<label>Name <input aria-label="n"></label>')->getBody()->all()[0];
        $this->assertInstanceOf(ContainerWidget::class, $root);
        [$text, $input] = $root->all();
        $this->assertSame('Name', $text->getText());
        $this->assertInstanceOf(InputWidget::class, $input);
    }

    public function testMixedContentKeepsItsOrder()
    {
        $root = $this->build('<div>text <b>x</b><p>para</p> tail </div>')->getBody()->all()[0];
        $children = $root->all();
        $this->assertCount(3, $children);
        $this->assertStringStartsWith('text ', $children[0]->getText());
        $this->assertSame('tui-text', $this->styleSheet->getDescription($children[0])[0]);
        $this->assertSame('para', $children[1]->getText());
        $this->assertSame('tail', $children[2]->getText());
    }

    public function testWhitespaceOnlyTextIsDropped()
    {
        $this->assertCount(1, $this->build("<div>\n  <p>a</p>\n  </div>")->getBody()->all());
    }

    public function testHorizontalRule()
    {
        $hr = $this->build('<hr>')->getBody()->all()[0];
        $this->assertInstanceOf(HorizontalRuleWidget::class, $hr);
        $this->assertSame(['hr'], $hr->getStyleClasses());
    }

    public function testInput()
    {
        $input = $this->build('<input aria-label="Name" value="Pierre">')->getBody()->all()[0];
        $this->assertInstanceOf(InputWidget::class, $input);
        $this->assertSame('Pierre', $input->getValue());
        $this->assertInstanceOf(InputWidget::class, $this->build('<input type="search" aria-label="q">')->getBody()->all()[0]);
    }

    public function testUnsupportedInputTypeIsReported()
    {
        $root = $this->build('<input type="nonsense" id="pick">')->getBody()->all()[0];
        $this->assertInstanceOf(ContainerWidget::class, $root);
        $this->assertSame([], $root->all());
        $this->assertSame('pick', $root->getId(), 'The fallback keeps the global attributes.');
        $this->assertTrue($this->logger->has('<input type="nonsense"> is not supported'));
    }

    public function testTextarea()
    {
        $editor = $this->build("<textarea aria-label=\"Body\" rows=\"4\">\nline 1\nline 2</textarea>")->getBody()->all()[0];
        $this->assertInstanceOf(EditorWidget::class, $editor);
        $this->assertSame("line 1\nline 2", $editor->getText());
        $this->assertSame(4, (new \ReflectionProperty(EditorWidget::class, 'minVisibleLines'))->getValue($editor));
    }

    public function testSelect()
    {
        $list = $this->build(<<<'HTML'
            <select aria-label="Language" size="3">
                <option value="php" title="Hypertext Preprocessor">PHP</option>
                <optgroup label="Systems">
                    <option value="rust" selected>Rust</option>
                    <option label="Go!">Go</option>
                </optgroup>
                <option disabled>Cobol</option>
            </select>
            HTML)->getBody()->all()[0];
        $this->assertInstanceOf(SelectListWidget::class, $list);
        $this->assertSame([['value' => 'php', 'label' => 'PHP', 'description' => 'Hypertext Preprocessor'], ['value' => 'rust', 'label' => 'Rust', 'group' => 'Systems'], ['value' => 'Go', 'label' => 'Go!', 'group' => 'Systems']], self::items($list));
        $this->assertSame('rust', $list->getSelectedItem()['value']);
        $this->assertSame(3, (new \ReflectionProperty(SelectListWidget::class, 'maxVisible'))->getValue($list));
    }

    public function testSelectMultiple()
    {
        $list = $this->build('<select multiple aria-label="L"><option selected>a</option><option>b</option><option selected>c</option></select>')->getBody()->all()[0];
        $this->assertSame(['a', 'c'], array_map(static fn (array $item) => $item['value'], $list->getSelectedItems()));
    }

    public function testProgress()
    {
        $bar = $this->build('<progress value="0.4"></progress>')->getBody()->all()[0];
        $this->assertInstanceOf(ProgressBarWidget::class, $bar);
        $this->assertSame(40, $bar->getProgress());
        $this->assertSame(100, $bar->getMaxSteps());
        $bar = $this->build('<progress value="3" max="12"></progress>')->getBody()->all()[0];
        $this->assertSame(3, $bar->getProgress());
        $this->assertSame(12, $bar->getMaxSteps());
        $indeterminate = $this->build('<progress></progress>')->getBody()->all()[0];
        $this->assertSame(0, $indeterminate->getMaxSteps(), 'No value: indeterminate.');
        $this->assertTrue($indeterminate->isRunning(), 'It moves, like a browser\'s.');
        $this->assertTrue($indeterminate->tick());
        $this->assertStringNotContainsString('1', preg_replace('/\e\[[0-9;]*m/', '', implode('', $indeterminate->render(new RenderContext(20, 1)))), 'No step count.');
        $this->assertStringContainsString('40%', preg_replace('/\e\[[0-9;]*m/', '', implode('', $this->build('<progress value="0.4"></progress>')->getBody()->all()[0]->render(new RenderContext(30, 1)))), 'A percentage, but no step count: the bar alone needs colors.');
    }

    public function testDetails()
    {
        $root = $this->build('<details open aria-describedby="d"><summary>More <b>info</b></summary><p>a</p><p>b</p></details><p id="d">Extra</p>')->getBody();
        $details = $root->all()[0];
        $this->assertInstanceOf(CollapsibleWidget::class, $details);
        $this->assertStringStartsWith('More ', $details->getSummary());
        $this->assertTrue($details->isExpanded());
        $this->assertCount(2, $details->getContent()->all());
        $this->assertSame('Extra', $details->getDescription());
        $closed = $this->build('<details><p>a</p></details>')->getBody()->all()[0];
        $this->assertFalse($closed->isExpanded());
        $this->assertSame('Details', $closed->getSummary());
    }

    public function testTabs()
    {
        $tabs = $this->build('<tui-tabs position="left"><section id="one" title="First"><p>1</p></section><section title="Second"><p>2</p></section></tui-tabs>')->getBody()->all()[0];
        $this->assertInstanceOf(TabsWidget::class, $tabs);
        $this->assertSame(['First', 'Second'], array_map(static fn (TabItem $t) => $t->getLabel(), $tabs->getTabs()));
        $this->assertSame('one', $tabs->getTabs()[0]->getId());
    }

    public function testLoader()
    {
        $loader = $this->build('<tui-loader>Loading <b>files</b></tui-loader>')->getBody()->all()[0];
        $this->assertInstanceOf(LoaderWidget::class, $loader);
        $this->assertStringStartsWith('Loading ', $loader->getMessage());
        $this->assertTrue($loader->isRunning());
        $this->assertFalse($this->build('<tui-loader stopped>Idle</tui-loader>')->getBody()->all()[0]->isRunning());
    }

    public function testMarkdownIsDedented()
    {
        $markdown = $this->build("<tui-markdown>\n    # Title\n\n      code\n    text\n</tui-markdown>")->getBody()->all()[0];
        $this->assertInstanceOf(MarkdownWidget::class, $markdown);
        $this->assertSame("# Title\n\n  code\ntext", $markdown->getText());
    }

    public function testTruncatingText()
    {
        $text = $this->build('<tui-text truncate>long</tui-text>')->getBody()->all()[0];
        $this->assertTrue((new \ReflectionProperty(TextWidget::class, 'truncate'))->getValue($text));
    }

    public function testLiveRegions()
    {
        $root = $this->build('<p role="status">a</p><p aria-live="assertive">b</p><p role="alert">c</p><p role="alert" aria-live="off">d</p><p role="log">e</p><p>f</p>')->getBody();
        $children = $root->all();
        $this->assertSame(['polite', 'assertive', 'assertive', null, 'polite', null], array_map(static fn (RichTextWidget $w) => $w->getPoliteness(), \array_slice($children, 0, 6)));
    }

    public function testUnknownElementIsReported()
    {
        $root = $this->build('<blink><p>a</p></blink>')->getBody()->all()[0];
        $this->assertInstanceOf(ContainerWidget::class, $root);
        $this->assertCount(1, $root->all());
        $this->assertSame('blink', $this->styleSheet->getDescription($root)[0]);
        $this->assertSame(['The <blink> element is not supported: its children are built in a container.'], $this->logger->messages);
    }

    public function testSkippedElements()
    {
        $root = $this->build('<p>a</p><script>alert(1)</script><template><p>t</p></template><p>b</p>')->getBody();
        $this->assertCount(2, $root->all());
    }

    public function testCustomElement()
    {
        $registry = new ElementRegistry();
        $registry->register('app-badge', static fn (\Dom\Element $e, HtmlBuilder $b) => new TextWidget('['.$e->getAttribute('label').']'));
        $builder = new HtmlBuilder($this->styleSheet, $registry);
        $badge = $builder->build('<app-badge label="new" class="hot"></app-badge>')->getBody()->all()[0];
        $this->assertSame('[new]', $badge->getText());
        $this->assertSame(['hot'], $badge->getStyleClasses());
        $this->assertSame('app-badge', $this->styleSheet->getDescription($badge)[0]);
        $this->assertSame($registry, $builder->getRegistry());
    }

    public function testStyleElementsAndInlineStyles()
    {
        $view = $this->build('<style>p { color: red }</style><style media="(max-width: 10)">p { font-weight: bold }</style><p>a</p><p style="color: blue">b</p>');
        [$a, $b] = $view->getBody()->all();
        $this->styleSheet->prepare($view->getRoot(), new MediaContext(80));
        $this->assertSame(Color::named('red')->toForegroundCode(), $this->styleSheet->getStyles($a)[0]->getColor()->toForegroundCode());
        $this->assertNull($this->styleSheet->getStyles($a)[0]->getBold());
        $this->assertSame(Color::named('blue')->toForegroundCode(), $this->styleSheet->getStyles($b)[0]->getColor()->toForegroundCode());
    }

    public function testLinkedStylesheets()
    {
        $dir = sys_get_temp_dir().'/tui-html-'.bin2hex(random_bytes(4));
        mkdir($dir.'/styles', 0o777, true);
        file_put_contents($dir.'/styles/app.css', '@import "base.css"; p { font-weight: bold }');
        file_put_contents($dir.'/base.css', 'p { color: red }');
        try {
            $styleSheet = new CssStyleSheet(new FilesystemStylesheetLoader([$dir.'/missing', $dir]), $this->logger);
            $builder = new HtmlBuilder($styleSheet, null, $this->logger);
            $view = $builder->build('<link rel="stylesheet" href="/styles/app.css"><link rel="icon" href="x.ico"><link rel="stylesheet" href="nope.css"><p>a</p>');
            $styleSheet->prepare($view->getRoot(), new MediaContext());
            $style = $styleSheet->getStyles($view->getBody()->all()[0])[0];
            $this->assertTrue($style->getBold());
            $this->assertSame(Color::named('red')->toForegroundCode(), $style->getColor()->toForegroundCode());
            $this->assertTrue($this->logger->has('"nope.css" was not found'));
        } finally {
            array_map('unlink', [$dir.'/styles/app.css', $dir.'/base.css']);
            rmdir($dir.'/styles');
            rmdir($dir);
        }
    }

    public function testLinkWithoutLoaderIsReported()
    {
        $this->build('<link rel="stylesheet" href="app.css"><p>a</p>');
        $this->assertTrue($this->logger->has('no stylesheet loader'));
    }

    public function testUserAgentStylesAreLoadedOnce()
    {
        $view = $this->build('<h2>a</h2>');
        $this->build('<h2>b</h2>');
        $this->styleSheet->prepare($view->getRoot(), new MediaContext());
        $this->assertTrue($this->styleSheet->getStyles($view->getBody()->all()[0])[0]->getBold());
    }

    #[DataProvider('provideAccessibleNames')]
    public function testAccessibleName(string $html, string $expected)
    {
        $view = $this->build($html);
        $this->assertSame($expected, $view->getById('control')->getLabel());
    }

    public static function provideAccessibleNames(): iterable
    {
        $all = '<span id="ref">Referenced</span><label for="control">For</label>';
        yield 'aria-label first' => [$all.'<input id="control" aria-label="Aria" aria-labelledby="ref" title="Title">', 'Aria'];
        yield 'then aria-labelledby' => [$all.'<input id="control" aria-labelledby="ref missing" title="Title">', 'Referenced'];
        yield 'then label for' => [$all.'<input id="control" title="Title">', 'For'];
        yield 'then wrapping label' => ['<label>Wrapping <input id="control" title="Title"></label>', 'Wrapping'];
        yield 'then title' => ['<input id="control" title="Title" alt="Alt">', 'Title'];
        yield 'then alt' => ['<div id="control" alt="Alt"></div>', 'Alt'];
    }

    public function testWrappingLabelWithoutId()
    {
        $label = $this->build('<label>Name <input></label>')->getBody()->all()[0];
        $this->assertSame('Name', $label->all()[1]->getLabel());
        $this->assertFalse($this->logger->has('accessible name'));
    }

    public function testUnnamedFocusableIsReported()
    {
        $this->build('<input id="a"><select><option>x</option></select><p>not focusable</p>');
        $this->assertSame(['The focusable <input> #a has no accessible name: add aria-label, a <label> or a title.', 'The focusable <select> has no accessible name: add aria-label, a <label> or a title.'], $this->logger->messages);
    }

    public function testDanglingLabelIsReported()
    {
        $this->build('<label for="nope">Name</label><input aria-label="x">');
        $this->assertTrue($this->logger->has('The <label for="nope"> does not label any element.'));
    }

    public function testDisabled()
    {
        $input = $this->build('<input disabled aria-label="x">')->getBody()->all()[0];
        $this->assertContains('disabled', $input->getStateFlags());
    }

    public function testEscapeSequencesAreStripped()
    {
        $root = $this->build("<p>\x1b[31mred\x1b]0;title\x07</p><input id=\"i\" aria-label=\"\x1b[2Jname\"><select aria-label=\"s\"><option value=\"\x1b[1mv\" title=\"\x1b[1md\">\x1b[1mo</option></select>")->getBody();
        // The input and the select, inline, share a line
        [$p, $line] = $root->all();
        [$input, $list] = $line->all();
        $this->assertStringNotContainsString("\x1b", $p->getText());
        $this->assertStringNotContainsString("\x07", $p->getText());
        $this->assertStringNotContainsString("\x1b", $input->getLabel());
        $this->assertStringNotContainsString("\x1b", json_encode(self::items($list)));
    }

    public function testDensityIsKeptForTheStyleSheet()
    {
        $view = $this->build('<style>p:condensed { color: red }</style><section data-density="condensed"><p>a</p></section><p>b</p>');
        [$section, $b] = $view->getBody()->all();
        $this->styleSheet->prepare($view->getRoot(), new MediaContext());
        $this->assertSame('condensed', $this->styleSheet->getDescription($section)[1]['data-density']);
        $this->assertSame(Color::named('red')->toForegroundCode(), $this->styleSheet->getStyles($section->all()[0])[0]->getColor()->toForegroundCode());
        // Its own styles, like the default margins of a paragraph, not those of the condensed one
        $this->assertFalse(array_any($this->styleSheet->getStyles($b), static fn ($style) => null !== $style->getColor()));
    }

    public function testAttributesAreKeptForSelectors()
    {
        $p = $this->build('<p data-kind="note" role="note" lang="fr" dir="rtl" aria-invalid="true">x</p>')->getBody()->all()[0];
        $this->assertSame(['data-kind' => 'note', 'role' => 'note', 'lang' => 'fr', 'dir' => 'rtl', 'aria-invalid' => 'true'], $this->styleSheet->getDescription($p)[1]);
    }

    public function testRegistrySupport()
    {
        $support = (new ElementRegistry())->getSupport();
        $this->assertArrayHasKey('html.elements.select', $support);
        $this->assertArrayHasKey('html.elements.tui-tabs', $support);
        $this->assertArrayHasKey('html.global_attributes.aria-label', $support);
        $this->assertNull((new ElementRegistry(false))->get('div'));
    }

    private function build(string $html): HtmlView
    {
        return $this->builder->build($html);
    }

    private static function items(AbstractWidget $list): array
    {
        return (new \ReflectionProperty(SelectListWidget::class, 'items'))->getValue($list instanceof DropdownWidget ? $list->getList() : $list);
    }
}
