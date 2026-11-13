<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Css;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Css\CssStyleSheet;
use Symfony\Component\Tui\Css\StylesheetLoaderInterface;
use Symfony\Component\Tui\Exception\InvalidArgumentException;
use Symfony\Component\Tui\Style\Color;
use Symfony\Component\Tui\Style\ColorScheme;
use Symfony\Component\Tui\Style\CursorShape;
use Symfony\Component\Tui\Style\Density;
use Symfony\Component\Tui\Style\MediaContext;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\InputWidget;
use Symfony\Component\Tui\Widget\SelectListWidget;
use Symfony\Component\Tui\Widget\TextWidget;

final class CssStyleSheetTest extends TestCase
{
    public function testDefaultElementNames()
    {
        $root = new ContainerWidget();
        $root->add($text = new TextWidget('x'));
        $root->add($input = new InputWidget());
        $root->add($list = new SelectListWidget([]));

        $css = CssStyleSheet::fromString('tui-root > tui-text { color: red } input { color: blue } select { color: green }');
        $css->prepare($root, new MediaContext());

        $this->assertColor('red', $css, $text);
        $this->assertColor('blue', $css, $input);
        $this->assertColor('green', $css, $list);
    }

    public function testTheRootIsNotOfItsClass()
    {
        $root = new ContainerWidget();
        $root->add($box = new ContainerWidget());

        $css = CssStyleSheet::fromString('div { color: red } :root { background: blue }');
        $css->prepare($root, new MediaContext());

        $this->assertNull($this->style($css, $root)->getColor(), 'A container at the root, like the one of the Tui, is the screen: div rules do not size or color it.');
        $this->assertSame(Color::named('blue')->toBackgroundCode(), $this->style($css, $root)->getBackground()?->toBackgroundCode());
        $this->assertColor('red', $css, $box);
    }

    public function testDescribeOverridesTheElement()
    {
        $root = new ContainerWidget();
        $root->add($title = new TextWidget('x'));

        $css = CssStyleSheet::fromString('h1[data-level="1"] { color: red }');
        $css->describe($title, 'H1', ['data-level' => '1']);
        $css->prepare($root, new MediaContext());

        $this->assertColor('red', $css, $title);
        $this->assertSame(['h1', ['data-level' => '1']], $css->getDescription($title));
    }

    public function testIdAndClassesComeFromTheWidget()
    {
        $root = new ContainerWidget();
        $root->add($text = (new TextWidget('x'))->setId('title')->addStyleClass('big'));

        $css = CssStyleSheet::fromString('#title.big { color: red }');
        $css->prepare($root, new MediaContext());

        $this->assertColor('red', $css, $text);
    }

    public function testSpecificityWins()
    {
        [$root, $text] = $this->tree('a');

        $css = CssStyleSheet::fromString('#a { color: red } tui-text { color: blue } .x { color: green }');
        $css->prepare($root, new MediaContext());

        $this->assertColor('red', $css, $text);
    }

    public function testLaterRuleWinsAtEqualSpecificity()
    {
        [$root, $text] = $this->tree();

        $css = CssStyleSheet::fromString('.x { color: red } .x { color: blue }');
        $css->prepare($root, new MediaContext());

        $this->assertColor('blue', $css, $text);
    }

    public function testLaterSourceWins()
    {
        [$root, $text] = $this->tree();

        $css = (new CssStyleSheet())->add('.x { color: red }')->add('.x { color: blue }');
        $css->prepare($root, new MediaContext());

        $this->assertColor('blue', $css, $text);
    }

    public function testImportantBeatsSpecificity()
    {
        [$root, $text] = $this->tree('a');

        $css = CssStyleSheet::fromString('.x { color: red !important } #a { color: blue }');
        $css->prepare($root, new MediaContext());

        $this->assertColor('red', $css, $text);
    }

    public function testInlineStyle()
    {
        [$root, $text] = $this->tree('a');

        $css = CssStyleSheet::fromString('#a { color: red; font-weight: bold } .x { font-style: italic !important }');
        $css->describe($text, 'p', ['style' => 'color: blue; font-style: normal']);
        $css->prepare($root, new MediaContext());

        $style = $this->style($css, $text);
        $this->assertSame(Color::named('blue')->toForegroundCode(), $style->getColor()->toForegroundCode(), 'Inline beats any selector.');
        $this->assertTrue($style->getBold());
        $this->assertTrue($style->getItalic(), '!important beats inline.');

        $css->describe($text, 'p', ['style' => 'font-style: normal !important']);
        $css->prepare($root, new MediaContext());
        $this->assertFalse($this->style($css, $text)->getItalic(), 'Inline !important beats everything.');
    }

    public function testInheritance()
    {
        $root = new ContainerWidget();
        $root->add($box = (new ContainerWidget())->addStyleClass('box'));
        $box->add($text = new TextWidget('x'));

        $css = CssStyleSheet::fromString(':root { --accent: cyan; color: red } .box { padding: 1; border: 1px solid var(--accent) } tui-text { background: var(--accent) }');
        $css->prepare($root, new MediaContext());

        $this->assertColor('red', $css, $text, 'color is inherited.');
        $this->assertNull($this->style($css, $text)->getPadding(), 'padding is not inherited.');
        $this->assertSame(Color::named('cyan')->toForegroundCode(), $this->style($css, $box)->getBorder()->color->toForegroundCode());
        $this->assertSame(Color::named('cyan')->toBackgroundCode(), $this->style($css, $text)->getBackground()->toBackgroundCode(), 'Custom properties are inherited.');
    }

    public function testCustomPropertyOverriddenInASubtree()
    {
        $root = new ContainerWidget();
        $root->add($box = (new ContainerWidget())->addStyleClass('box'));
        $box->add($inner = new TextWidget('x'));
        $root->add($outer = new TextWidget('y'));

        $css = CssStyleSheet::fromString(':root { --c: red } .box { --c: blue } tui-text { color: var(--c) }');
        $css->prepare($root, new MediaContext());

        $this->assertColor('blue', $css, $inner);
        $this->assertColor('red', $css, $outer);
    }

    public function testExplicitInherit()
    {
        $root = new ContainerWidget();
        $root->addStyleClass('root');
        $root->add($text = new TextWidget('x'));

        $css = CssStyleSheet::fromString('.root { padding: 2 } tui-text { padding: inherit; color: inherit }');
        $css->prepare($root, new MediaContext());

        $this->assertSame(2, $this->style($css, $text)->getPadding()->top);
        $this->assertSame(Color::named('default')->toForegroundCode(), $this->style($css, $text)->getColor()?->toForegroundCode(), 'Nothing to inherit: the initial value, the terminal color.');
    }

    public function testUndefinedVarDropsTheDeclaration()
    {
        [$root, $text] = $this->tree();

        $css = CssStyleSheet::fromString('.x { color: var(--nope); font-weight: bold }');
        $css->prepare($root, new MediaContext());

        $this->assertNull($this->style($css, $text)->getColor());
        $this->assertTrue($this->style($css, $text)->getBold());
    }

    public function testNoMatchNoStyle()
    {
        [$root, $text] = $this->tree();

        $css = CssStyleSheet::fromString('p { color: red }');
        $css->prepare($root, new MediaContext());

        $this->assertSame([], $css->getStyles($text));
    }

    public function testTheCaretTakesTheCaretPropertiesOfItsField()
    {
        $root = new ContainerWidget();
        $root->add($form = (new ContainerWidget())->addStyleClass('form'));
        $form->add($input = new InputWidget());
        $root->add($other = new InputWidget());

        $css = CssStyleSheet::fromString('.form { caret-shape: underscore; caret-color: #123456 } input:focus { caret-animation: manual }');
        $css->prepare($root, new MediaContext());

        $this->assertSame(CursorShape::Underline, $css->getElementStyles($input, 'cursor')[0]->getCursorShape(), 'Inherited from an ancestor of the field.');
        $this->assertSame('#123456', $css->getElementStyles($input, 'cursor')[0]->getCaretColor()?->toHex());
        $this->assertSame([], $css->getElementStyles($other, 'cursor'));
        $this->assertSame([], $css->getElementStyles($input, 'placeholder'));

        $input->setFocused(true);
        $css->prepare($root, new MediaContext());
        $this->assertSame(CursorShape::SteadyUnderline, $css->getElementStyles($input, 'cursor')[0]->getCursorShape());
    }

    public function testPseudoElements()
    {
        $root = new ContainerWidget();
        $root->add($list = (new SelectListWidget([]))->addStyleClass('menu'));

        $css = CssStyleSheet::fromString('.menu { color: red } select::selected { font-weight: bold } .menu::selected:focus { text-decoration: underline } select::description { color: inherit }');
        $css->prepare($root, new MediaContext());

        $selected = $css->getElementStyles($list, 'selected');
        $this->assertCount(1, $selected);
        $this->assertTrue($selected[0]->getBold());
        $this->assertNull($selected[0]->getUnderline());
        $this->assertColor('red', $css, $list);
        $this->assertSame(Color::named('red')->toForegroundCode(), $css->getElementStyles($list, 'description')[0]->getColor()->toForegroundCode(), 'A pseudo-element inherits from its widget.');
        $this->assertSame([], $css->getElementStyles($list, 'scroll-info'));

        $list->setFocused(true);
        $css->prepare($root, new MediaContext());
        $this->assertTrue($css->getElementStyles($list, 'selected')[0]->getUnderline());
    }

    public function testThroughStyleSheet()
    {
        $root = new ContainerWidget();
        $root->add($list = new SelectListWidget([]));

        $css = CssStyleSheet::fromString('select { color: red } select::selected { color: blue }');
        $sheet = new StyleSheet([SelectListWidget::class => new Style(bold: true), SelectListWidget::class.'::selected' => new Style(italic: true)]);
        $sheet->addProvider($css);
        $sheet->addProvider($css);
        $this->assertCount(1, $sheet->getProviders());

        $css->prepare($root, new MediaContext());

        $resolved = $sheet->resolve($list);
        $this->assertTrue($resolved->getBold());
        $this->assertSame(Color::named('red')->toForegroundCode(), $resolved->getColor()->toForegroundCode());

        $element = $sheet->resolveElement($list, 'selected');
        $this->assertTrue($element->getItalic());
        $this->assertSame(Color::named('blue')->toForegroundCode(), $element->getColor()->toForegroundCode());

        $list->setStyle(new Style(color: 'green'));
        $this->assertSame(Color::named('green')->toForegroundCode(), $sheet->resolve($list)->getColor()->toForegroundCode(), 'The instance style still wins.');
    }

    public function testMergeKeepsProviders()
    {
        $css = new CssStyleSheet();
        $other = new StyleSheet();
        $other->addProvider($css);

        $this->assertSame([$css], (new StyleSheet())->merge($other)->getProviders());
    }

    public function testFocusWithinAndHover()
    {
        $root = new ContainerWidget();
        $root->add($card = (new ContainerWidget())->addStyleClass('card'));
        $card->add($input = new InputWidget());
        $card->add($text = new TextWidget('x'));

        $css = CssStyleSheet::fromString('.card:focus-within { color: red } tui-text:hover { color: blue } input:focus { font-weight: bold }');
        $css->prepare($root, new MediaContext());
        $this->assertSame([], $css->getStyles($card));
        $this->assertSame([], $css->getStyles($input));

        $input->setFocused(true);
        $text->setState('hover');
        $css->prepare($root, new MediaContext());
        $this->assertColor('red', $css, $card);
        $this->assertTrue($this->style($css, $input)->getBold());
        $this->assertColor('blue', $css, $text);

        $input->setFocused(false);
        $text->setState('hover', false);
        $css->prepare($root, new MediaContext());
        $this->assertSame([], $css->getStyles($card));
        $this->assertSame([], $css->getStyles($text));
    }

    public function testCustomStateAndDisabled()
    {
        [$root, $text] = $this->tree();

        $css = CssStyleSheet::fromString('.x:loading { color: red } .x:enabled { font-weight: bold } .x:disabled { opacity: .5 }');
        $css->prepare($root, new MediaContext());
        $this->assertTrue($this->style($css, $text)->getBold());

        $text->setState('loading')->setState('disabled');
        $css->prepare($root, new MediaContext());
        $this->assertColor('red', $css, $text);
        $this->assertNull($this->style($css, $text)->getBold());
        $this->assertTrue($this->style($css, $text)->getDim());
    }

    public function testCondensedFromDensityAttribute()
    {
        $root = new ContainerWidget();
        $root->add($box = new ContainerWidget());
        $box->add($inner = new TextWidget('x'));
        $box->add($regular = new TextWidget('y'));
        $root->add($outside = new TextWidget('z'));

        $css = CssStyleSheet::fromString('tui-text:condensed { color: red }');
        $css->describe($box, 'div', ['data-density' => 'condensed']);
        $css->describe($regular, 'tui-text', ['data-density' => 'regular']);
        $css->prepare($root, new MediaContext());

        $this->assertColor('red', $css, $inner, 'The density is inherited.');
        $this->assertSame([], $css->getStyles($regular), 'The nearest density wins.');
        $this->assertSame([], $css->getStyles($outside));

        $css->prepare($root, new MediaContext(density: Density::Condensed));
        $this->assertColor('red', $css, $outside, 'Without an attribute, the media context density applies.');
        $this->assertSame([], $css->getStyles($regular));
    }

    public function testMediaChangesRematch()
    {
        [$root, $text] = $this->tree();

        $css = CssStyleSheet::fromString('.x { color: light-dark(red, blue) } @media (max-width: 40) { .x { font-weight: bold } } @media (prefers-color-scheme: light) { .x { font-style: italic } }');

        $css->prepare($root, new MediaContext(80, 24));
        $this->assertColor('blue', $css, $text);
        $this->assertNull($this->style($css, $text)->getBold());

        $css->prepare($root, new MediaContext(30, 24));
        $this->assertTrue($this->style($css, $text)->getBold());

        $css->prepare($root, new MediaContext(80, 24, ColorScheme::Light));
        $this->assertColor('red', $css, $text);
        $this->assertTrue($this->style($css, $text)->getItalic());
        $this->assertNull($this->style($css, $text)->getBold());
    }

    public function testMediaAttributeOfASource()
    {
        [$root, $text] = $this->tree();

        $css = (new CssStyleSheet())->add('.x { color: red }', '(min-width: 100)');

        $css->prepare($root, new MediaContext(80));
        $this->assertSame([], $css->getStyles($text));
        $css->prepare($root, new MediaContext(120));
        $this->assertColor('red', $css, $text);
    }

    public function testAddingTheSameSourceTwiceIsANoop()
    {
        [$root, $text] = $this->tree();

        $css = (new CssStyleSheet())->add('.x { color: red }')->add('.x { color: blue }')->add('.x { color: red }');
        $css->prepare($root, new MediaContext());

        $this->assertColor('blue', $css, $text);
    }

    public function testAddingRulesRematches()
    {
        [$root, $text] = $this->tree();

        $css = CssStyleSheet::fromString('.x { color: red }');
        $css->prepare($root, new MediaContext());
        $css->add('.x { color: blue }');
        $css->prepare($root, new MediaContext());

        $this->assertColor('blue', $css, $text);
    }

    public function testOnlyWidgetsWhoseStyleChangedAreInvalidated()
    {
        $root = new ContainerWidget();
        $root->add($left = (new ContainerWidget())->addStyleClass('left'));
        $left->add($leftText = new TextWidget('a'));
        $root->add($right = (new ContainerWidget())->addStyleClass('right'));
        $right->add($rightText = new TextWidget('b'));
        $right->add($input = new InputWidget());

        $css = CssStyleSheet::fromString('.left { color: red } .right:focus-within { color: blue }');
        $media = new MediaContext();
        $css->prepare($root, $media);

        $revisions = [$left->getRenderRevision(), $leftText->getRenderRevision(), $rightText->getRenderRevision()];
        $input->setFocused(true);
        $css->prepare($root, $media);

        $this->assertColor('blue', $css, $right);
        $this->assertSame($revisions[0], $left->getRenderRevision(), 'An untouched widget keeps its render cache.');
        $this->assertSame($revisions[1], $leftText->getRenderRevision());
        $this->assertSame($revisions[2] + 1, $rightText->getRenderRevision(), 'An inherited color change invalidates the child.');
    }

    public function testPrepareIsANoopWhenNothingChanged()
    {
        [$root, $text] = $this->tree();

        $css = CssStyleSheet::fromString('.x { color: red }');
        $media = new MediaContext();
        $css->prepare($root, $media);
        $revision = $root->getRenderRevision();

        $css->prepare($root, $media);
        $css->prepare($root, new MediaContext());

        $this->assertSame($revision, $root->getRenderRevision());
        $this->assertColor('red', $css, $text);
    }

    public function testUnrelatedInvalidationDoesNotRestyle()
    {
        [$root, $text] = $this->tree();
        $root->add($other = new TextWidget('b'));

        $css = CssStyleSheet::fromString('.x { color: red }');
        $media = new MediaContext();
        $css->prepare($root, $media);
        $revision = $text->getRenderRevision();

        $other->setText('c');
        $css->prepare($root, $media);

        $this->assertSame($revision, $text->getRenderRevision());
    }

    public function testStructureChangesRematch()
    {
        [$root, $text] = $this->tree();

        $css = CssStyleSheet::fromString('tui-text:last-child { color: red }');
        $css->prepare($root, new MediaContext());
        $this->assertColor('red', $css, $text);

        $root->add($last = new TextWidget('b'));
        $css->prepare($root, new MediaContext());
        $this->assertSame([], $css->getStyles($text));
        $this->assertColor('red', $css, $last);

        $root->remove($last);
        $css->prepare($root, new MediaContext());
        $this->assertColor('red', $css, $text);
    }

    public function testRootPseudoClass()
    {
        [$root, $text] = $this->tree();

        $css = CssStyleSheet::fromString(':root { color: red } tui-text:root { color: blue }');
        $css->prepare($root, new MediaContext());

        $this->assertColor('red', $css, $root);
        $this->assertColor('red', $css, $text, 'Inherited, not matched.');
    }

    public function testQuerySelectorAll()
    {
        $root = new ContainerWidget();
        $root->add($a = (new TextWidget('a'))->addStyleClass('item'));
        $root->add($box = new ContainerWidget());
        $box->add($b = (new TextWidget('b'))->addStyleClass('item'));

        $css = new CssStyleSheet();
        $css->prepare($root, new MediaContext());

        $this->assertSame([$a, $b], $css->querySelectorAll('.item'));
        $this->assertSame([$b], $css->querySelectorAll('tui-root > div > .item'));
        $this->assertSame([$b], $css->querySelectorAll('.item', $box), 'Scoped to the descendants of a widget.');
        $this->assertSame([$a, $b], $css->querySelectorAll('tui-text:first-child, .item'));
        $this->assertSame([], (new CssStyleSheet())->querySelectorAll('.item'), 'Nothing mirrored yet.');
    }

    public function testQuerySelectorAllOnAnotherTree()
    {
        $root = new ContainerWidget();
        $root->add($a = (new TextWidget('a'))->addStyleClass('item'));

        $this->assertSame([$a], (new CssStyleSheet())->querySelectorAll('.item', $root));
    }

    public function testImport()
    {
        [$root, $text] = $this->tree();
        $loader = new class implements StylesheetLoaderInterface {
            public function load(string $href): string
            {
                return '.x { color: red }';
            }
        };

        $css = (new CssStyleSheet($loader))->import('app.css');
        $css->prepare($root, new MediaContext());

        $this->assertColor('red', $css, $text);
    }

    public function testImportWithoutLoader()
    {
        $this->expectException(InvalidArgumentException::class);

        (new CssStyleSheet())->import('app.css');
    }

    public function testSupport()
    {
        $support = (new CssStyleSheet())->getSupport();

        $this->assertArrayHasKey('css.selectors.focus-within', $support);
        $this->assertArrayHasKey('css.at-rules.media.resolution', $support);
        $this->assertArrayHasKey('css.at-rules.import', $support);
        $this->assertArrayHasKey('css.properties.color', $support);
    }

    /**
     * @return array{ContainerWidget, TextWidget}
     */
    private function tree(?string $id = null): array
    {
        $root = new ContainerWidget();
        $text = (new TextWidget('a'))->addStyleClass('x');
        if (null !== $id) {
            $text->setId($id);
        }
        $root->add($text);

        return [$root, $text];
    }

    private function style(CssStyleSheet $css, AbstractWidget $widget): Style
    {
        $styles = $css->getStyles($widget);
        $this->assertCount(1, $styles);

        return $styles[0];
    }

    private function assertColor(string $expected, CssStyleSheet $css, AbstractWidget $widget, string $message = ''): void
    {
        $this->assertSame(Color::named($expected)->toForegroundCode(), $this->style($css, $widget)->getColor()?->toForegroundCode(), $message);
    }
}
