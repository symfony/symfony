<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Style;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Style\Color;
use Symfony\Component\Tui\Style\Direction;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\InputWidget;
use Symfony\Component\Tui\Widget\SelectListWidget;
use Symfony\Component\Tui\Widget\TextWidget;

class StyleSheetTest extends TestCase
{
    public function testResolveWithNoRules()
    {
        $stylesheet = new StyleSheet();
        $widget = new TextWidget('Hello');

        $resolved = $stylesheet->resolve($widget);

        $this->assertNull($resolved->getPadding());
        $this->assertNull($resolved->getBorder());
        $this->assertNull($resolved->getBackground());
        $this->assertNull($resolved->getColor());
    }

    public function testResolveWithUniversalSelector()
    {
        $stylesheet = new StyleSheet()
            ->addRule('*', Style::padding([1, 2]));
        $widget = new TextWidget('Hello');

        $resolved = $stylesheet->resolve($widget);

        $this->assertSame(1, $resolved->getPadding()->top);
        $this->assertSame(2, $resolved->getPadding()->right);
    }

    public function testResolveWithFqcnSelector()
    {
        $stylesheet = new StyleSheet()
            ->addRule(TextWidget::class, new Style()->withColor('red'));
        $widget = new TextWidget('Hello');

        $resolved = $stylesheet->resolve($widget);

        $this->assertSame(Color::named('red')->toForegroundCode(), $resolved->getColor()->toForegroundCode());
    }

    public function testResolveWithClassSelector()
    {
        $stylesheet = new StyleSheet()
            ->addRule('.header', new Style()->withBold());
        $widget = new TextWidget('Hello')
            ->addStyleClass('header');

        $resolved = $stylesheet->resolve($widget);

        $this->assertTrue($resolved->getBold());
    }

    public function testMergePaddingInheritance()
    {
        // Base rule sets padding
        // Override rule doesn't set padding (null) -> should inherit
        $stylesheet = new StyleSheet()
            ->addRule('*', Style::padding([2]))
            ->addRule(TextWidget::class, new Style()->withColor('red'));
        $widget = new TextWidget('Hello');

        $resolved = $stylesheet->resolve($widget);

        // Should inherit padding from universal selector
        $this->assertSame(2, $resolved->getPadding()->top);
        // And have color from FQCN selector
        $this->assertSame(Color::named('red')->toForegroundCode(), $resolved->getColor()->toForegroundCode());
    }

    public function testMergePaddingExplicitZero()
    {
        // Base rule sets padding
        // Override rule explicitly sets padding to zero -> should override, not inherit
        $stylesheet = new StyleSheet()
            ->addRule('*', Style::padding([2]))
            ->addRule(TextWidget::class, Style::padding([0]));
        $widget = new TextWidget('Hello');

        $resolved = $stylesheet->resolve($widget);

        // Should use explicit zero padding, not inherit
        $this->assertSame(0, $resolved->getPadding()->top);
        $this->assertSame(0, $resolved->getPadding()->right);
        $this->assertSame(0, $resolved->getPadding()->bottom);
        $this->assertSame(0, $resolved->getPadding()->left);
    }

    public function testMergeBorderInheritance()
    {
        // Base rule sets border
        // Override rule doesn't set border (null) -> should inherit
        $stylesheet = new StyleSheet()
            ->addRule('*', Style::border([1]))
            ->addRule(TextWidget::class, new Style()->withColor('blue'));
        $widget = new TextWidget('Hello');

        $resolved = $stylesheet->resolve($widget);

        // Should inherit border from universal selector
        $this->assertSame(1, $resolved->getBorder()->top);
    }

    public function testMergeBorderExplicitZero()
    {
        // Base rule sets border
        // Override rule explicitly sets border to zero -> should override, not inherit
        $stylesheet = new StyleSheet()
            ->addRule('*', Style::border([1]))
            ->addRule(TextWidget::class, Style::border([0]));
        $widget = new TextWidget('Hello');

        $resolved = $stylesheet->resolve($widget);

        // Should use explicit zero border, not inherit
        $this->assertSame(0, $resolved->getBorder()->top);
        $this->assertSame(0, $resolved->getBorder()->right);
        $this->assertSame(0, $resolved->getBorder()->bottom);
        $this->assertSame(0, $resolved->getBorder()->left);
    }

    public function testMergeColorInheritance()
    {
        // Base rule sets color
        // Override rule doesn't set color (null) -> should inherit
        $stylesheet = new StyleSheet()
            ->addRule('*', new Style()->withColor('red'))
            ->addRule(TextWidget::class, new Style()->withBold());
        $widget = new TextWidget('Hello');

        $resolved = $stylesheet->resolve($widget);

        // Should inherit color and have bold
        $this->assertSame(Color::named('red')->toForegroundCode(), $resolved->getColor()->toForegroundCode());
        $this->assertTrue($resolved->getBold());
    }

    public function testMergeMultipleClasses()
    {
        $stylesheet = new StyleSheet()
            ->addRule('.card', Style::padding([1])->withBackground('blue'))
            ->addRule('.highlight', new Style()->withColor('yellow')->withBold());
        $widget = new TextWidget('Hello')
            ->addStyleClass('card')
            ->addStyleClass('highlight');

        $resolved = $stylesheet->resolve($widget);

        // Should have padding and background from .card
        $this->assertSame(1, $resolved->getPadding()->top);
        $this->assertSame(Color::named('blue')->toForegroundCode(), $resolved->getBackground()->toForegroundCode());
        // And color and bold from .highlight
        $this->assertSame(Color::named('yellow')->toForegroundCode(), $resolved->getColor()->toForegroundCode());
        $this->assertTrue($resolved->getBold());
    }

    public function testInstanceStyleOverridesStylesheet()
    {
        $stylesheet = new StyleSheet()
            ->addRule('*', Style::padding([2])->withColor('red'));
        $widget = new TextWidget('Hello');

        $widget->setStyle(Style::padding([5])->withColor('blue'));
        $resolved = $stylesheet->resolve($widget);

        // Instance style should override stylesheet
        $this->assertSame(5, $resolved->getPadding()->top);
        $this->assertSame(Color::named('blue')->toForegroundCode(), $resolved->getColor()->toForegroundCode());
    }

    public function testInstanceStyleExplicitZeroOverridesStylesheet()
    {
        $stylesheet = new StyleSheet()
            ->addRule('*', Style::padding([2]));
        $widget = new TextWidget('Hello');

        // Instance style explicitly sets padding to zero
        $widget->setStyle(Style::padding([0]));
        $resolved = $stylesheet->resolve($widget);

        // Instance style's explicit zero should override stylesheet's padding
        $this->assertSame(0, $resolved->getPadding()->top);
    }

    /**
     * @param \Closure(Style): ?bool $getter
     */
    #[DataProvider('boolPropertyInheritanceProvider')]
    public function testBoolPropertyInheritance(string $method, \Closure $getter)
    {
        // Base rule sets property, override doesn't -> should inherit
        $stylesheet = new StyleSheet()
            ->addRule('*', new Style()->$method())
            ->addRule(TextWidget::class, new Style()->withColor('red'));

        $resolved = $stylesheet->resolve(new TextWidget('Hello'));

        $this->assertTrue($getter($resolved));
        $this->assertSame(Color::named('red')->toForegroundCode(), $resolved->getColor()->toForegroundCode());
    }

    /**
     * @param \Closure(Style): ?bool $getter
     */
    #[DataProvider('boolPropertyInheritanceProvider')]
    public function testBoolPropertyExplicitFalse(string $method, \Closure $getter)
    {
        // Base rule sets true, override explicitly sets false -> should override
        $stylesheet = new StyleSheet()
            ->addRule('*', new Style()->$method(true))
            ->addRule(TextWidget::class, new Style()->$method(false));

        $resolved = $stylesheet->resolve(new TextWidget('Hello'));

        $this->assertFalse($getter($resolved));
    }

    /**
     * @return iterable<string, array{string, \Closure(Style): ?bool}>
     */
    public static function boolPropertyInheritanceProvider(): iterable
    {
        yield 'bold' => ['withBold', static fn (Style $s) => $s->getBold()];
        yield 'italic' => ['withItalic', static fn (Style $s) => $s->getItalic()];
        yield 'dim' => ['withDim', static fn (Style $s) => $s->getDim()];
    }

    // --- Standalone pseudo-class selector tests ---

    public function testRootPseudoClassMatchesRootWidget()
    {
        $stylesheet = new StyleSheet()
            ->addRule(':root', new Style()->withBold());

        // A widget without parent is the root
        $widget = new TextWidget('Hello');

        $resolved = $stylesheet->resolve($widget);

        $this->assertTrue($resolved->getBold());
    }

    public function testRootPseudoClassDoesNotMatchChildWidget()
    {
        $stylesheet = new StyleSheet()
            ->addRule(':root', new Style()->withBold());

        $parent = new ContainerWidget();
        $child = new TextWidget('Hello');
        $parent->add($child);

        $resolved = $stylesheet->resolve($child);

        $this->assertNull($resolved->getBold());
    }

    // --- Cascading Stylesheet Tests (via merge) ---

    public function testMergeSheetsRulesOverride()
    {
        // Default sheet
        $defaultSheet = new StyleSheet()
            ->addRule('*', Style::padding([2])->withColor('red'));

        // User sheet overrides
        $userSheet = new StyleSheet()
            ->addRule('*', Style::padding([5])->withColor('blue'));

        // Merge: user rules override default rules for the same selector
        $merged = $defaultSheet->merge($userSheet);

        $widget = new TextWidget('Hello');
        $resolved = $merged->resolve($widget);

        // Should have padding from user sheet (overriding default)
        $this->assertSame(5, $resolved->getPadding()->top);
        // Should have color from user sheet (overriding default)
        $this->assertSame(Color::named('blue')->toForegroundCode(), $resolved->getColor()->toForegroundCode());
    }

    public function testMergeSheetsNewRulesAdded()
    {
        // Default sheet
        $defaultSheet = new StyleSheet()
            ->addRule('*', Style::padding([2]))
            ->addRule(TextWidget::class, new Style()->withColor('red'));

        // User sheet adds new rules
        $userSheet = new StyleSheet()
            ->addRule('.header', new Style()->withBold());

        $merged = $defaultSheet->merge($userSheet);

        $widget = new TextWidget('Hello')
            ->addStyleClass('header');

        $resolved = $merged->resolve($widget);

        // Should have padding from default sheet
        $this->assertSame(2, $resolved->getPadding()->top);
        // Should have color from default sheet
        $this->assertSame(Color::named('red')->toForegroundCode(), $resolved->getColor()->toForegroundCode());
        // Should have bold from user sheet
        $this->assertTrue($resolved->getBold());
    }

    public function testMergeMultipleSheets()
    {
        // Three sheets merged in order
        $base = new StyleSheet()
            ->addRule('*', new Style()->withBold());

        $theme = new StyleSheet()
            ->addRule('*', new Style()->withColor('red'));

        $user = new StyleSheet()
            ->addRule('*', Style::padding([1]));

        $merged = $base->merge($theme)->merge($user);

        $widget = new TextWidget('Hello');
        $resolved = $merged->resolve($widget);

        // Last sheet's universal rule wins
        $this->assertSame(1, $resolved->getPadding()->top);
        // Previous universal rules are replaced, not property-merged
        $this->assertNull($resolved->getColor());
        $this->assertNull($resolved->getBold());
    }

    public function testMergePreservesNonOverlappingSelectors()
    {
        $defaultSheet = new StyleSheet()
            ->addRule(TextWidget::class, Style::padding([1, 2])->withColor('red')->withBold());

        // User sheet adds a different selector
        $userSheet = new StyleSheet()
            ->addRule('.highlight', new Style()->withColor('yellow'));

        $merged = $defaultSheet->merge($userSheet);

        $widget = new TextWidget('Hello')
            ->addStyleClass('highlight');

        $resolved = $merged->resolve($widget);

        // Should have padding and bold from FQCN selector
        $this->assertSame(1, $resolved->getPadding()->top);
        $this->assertTrue($resolved->getBold());
        // Color from .highlight overrides FQCN color (class selectors > FQCN)
        $this->assertSame(Color::named('yellow')->toForegroundCode(), $resolved->getColor()->toForegroundCode());
    }

    public function testMergeStateSelectors()
    {
        // Default sheet
        $defaultSheet = new StyleSheet()
            ->addRule(InputWidget::class.':focus', new Style()->withBold());

        // User sheet
        $userSheet = new StyleSheet()
            ->addRule(InputWidget::class.':focus', new Style()->withColor('yellow'));

        // Merge replaces the state selector rule
        $merged = $defaultSheet->merge($userSheet);

        // Create a focused widget
        $widget = new InputWidget();
        $widget->setFocused(true);

        $resolved = $merged->resolve($widget);

        // User sheet replaced the rule, so bold is gone
        $this->assertNull($resolved->getBold());
        $this->assertSame(Color::named('yellow')->toForegroundCode(), $resolved->getColor()->toForegroundCode());
    }

    public function testMergeWithInstanceStyle()
    {
        $defaultSheet = new StyleSheet()
            ->addRule('*', Style::padding([2])->withColor('red'));

        $userSheet = new StyleSheet()
            ->addRule('*', new Style()->withBold());

        // After merge, universal rule is from user sheet (bold only)
        $merged = $defaultSheet->merge($userSheet);

        // Widget with instance style
        $widget = new TextWidget('Hello');
        $widget->setStyle(new Style()->withColor('blue'));

        $resolved = $merged->resolve($widget);

        // Universal rule is now bold only (user sheet replaced it)
        $this->assertTrue($resolved->getBold());
        // Color from widget's instance style
        $this->assertSame(Color::named('blue')->toForegroundCode(), $resolved->getColor()->toForegroundCode());
        // Padding is gone (user sheet's universal rule doesn't have it)
        $this->assertNull($resolved->getPadding());
    }

    public function testMergeDoesNotAffectSourceSheet()
    {
        $base = new StyleSheet()
            ->addRule('*', Style::padding([2]));

        $extra = new StyleSheet()
            ->addRule('.header', new Style()->withBold());

        $base->merge($extra);

        // The source sheet should be unchanged
        $this->assertCount(1, $extra->getRules());
        $this->assertArrayHasKey('.header', $extra->getRules());
    }

    public function testGetRulesReturnsAllRulesAfterMerge()
    {
        $sheet1 = new StyleSheet()
            ->addRule('*', Style::padding([1]))
            ->addRule('.a', new Style()->withBold());

        $sheet2 = new StyleSheet()
            ->addRule('.b', new Style()->withItalic())
            ->addRule('.a', new Style()->withColor('red')); // overrides

        $sheet1->merge($sheet2);

        $rules = $sheet1->getRules();
        $this->assertCount(3, $rules);
        $this->assertArrayHasKey('*', $rules);
        $this->assertArrayHasKey('.a', $rules);
        $this->assertArrayHasKey('.b', $rules);

        // .a was overridden by sheet2's rule
        $this->assertSame(Color::named('red')->toForegroundCode(), $rules['.a']->getColor()->toForegroundCode());
        $this->assertNull($rules['.a']->getBold());
    }

    // --- Sub-element (pseudo-element) resolution tests ---

    public function testResolveElementByFqcn()
    {
        $stylesheet = new StyleSheet([
            TextWidget::class.'::heading' => new Style()->withBold()->withColor('cyan'),
        ]);

        $widget = new TextWidget('Hello');
        $style = $stylesheet->resolveElement($widget, 'heading');

        $this->assertTrue($style->getBold());
        $this->assertSame(Color::named('cyan')->toForegroundCode(), $style->getColor()->toForegroundCode());
    }

    public function testResolveElementReturnsEmptyForNoRules()
    {
        $stylesheet = new StyleSheet();
        $widget = new TextWidget('Hello');

        $style = $stylesheet->resolveElement($widget, 'heading');

        $this->assertNull($style->getBold());
        $this->assertNull($style->getColor());
    }

    public function testResolveElementByCssClass()
    {
        $stylesheet = new StyleSheet([
            '.my-list::selected' => new Style()->withColor('green'),
        ]);

        $items = [['value' => 'a', 'label' => 'A']];
        $widget = new SelectListWidget($items);
        $widget->addStyleClass('my-list');

        $style = $stylesheet->resolveElement($widget, 'selected');

        $this->assertSame(Color::named('green')->toForegroundCode(), $style->getColor()->toForegroundCode());
    }

    public function testResolveElementFqcnAndClassMerge()
    {
        // FQCN rule sets bold, CSS class rule sets color
        $stylesheet = new StyleSheet([
            SelectListWidget::class.'::selected' => new Style()->withBold(),
            '.custom-list::selected' => new Style()->withColor('red'),
        ]);

        $items = [['value' => 'a', 'label' => 'A']];
        $widget = new SelectListWidget($items);
        $widget->addStyleClass('custom-list');

        $style = $stylesheet->resolveElement($widget, 'selected');

        // Both should be merged: bold from FQCN, color from class
        $this->assertTrue($style->getBold());
        $this->assertSame(Color::named('red')->toForegroundCode(), $style->getColor()->toForegroundCode());
    }

    public function testResolveElementWithStateFlag()
    {
        $stylesheet = new StyleSheet([
            InputWidget::class.'::cursor' => new Style()->withReverse(),
            InputWidget::class.'::cursor:focus' => new Style()->withColor('cyan'),
        ]);

        // Unfocused: only reverse
        $widget = new InputWidget();
        $style = $stylesheet->resolveElement($widget, 'cursor');
        $this->assertTrue($style->getReverse());
        $this->assertNull($style->getColor());

        // Focused: reverse + color
        $widget->setFocused(true);
        $style = $stylesheet->resolveElement($widget, 'cursor');
        $this->assertTrue($style->getReverse());
        $this->assertSame(Color::named('cyan')->toForegroundCode(), $style->getColor()->toForegroundCode());
    }

    public function testResolveElementClassWithStateFlag()
    {
        $stylesheet = new StyleSheet([
            '.my-input::cursor' => new Style()->withReverse(),
            '.my-input::cursor:focus' => new Style()->withColor('yellow'),
        ]);

        $widget = new InputWidget();
        $widget->addStyleClass('my-input');

        // Unfocused
        $style = $stylesheet->resolveElement($widget, 'cursor');
        $this->assertTrue($style->getReverse());
        $this->assertNull($style->getColor());

        // Focused
        $widget->setFocused(true);
        $style = $stylesheet->resolveElement($widget, 'cursor');
        $this->assertTrue($style->getReverse());
        $this->assertSame(Color::named('yellow')->toForegroundCode(), $style->getColor()->toForegroundCode());
    }

    public function testResolveElementCascadeOrder()
    {
        // Class rule overrides FQCN rule, state overrides both
        $stylesheet = new StyleSheet([
            SelectListWidget::class.'::selected' => new Style()->withColor('red'),
            '.themed::selected' => new Style()->withColor('blue'),
            '.themed::selected:focus' => new Style()->withColor('green'),
        ]);

        $items = [['value' => 'a', 'label' => 'A']];
        $widget = new SelectListWidget($items);
        $widget->addStyleClass('themed');

        // Unfocused: .themed::selected overrides FQCN::selected
        $style = $stylesheet->resolveElement($widget, 'selected');
        // Blue from .themed overrides red from FQCN
        $this->assertSame(Color::named('blue')->toForegroundCode(), $style->getColor()->toForegroundCode());

        // Focused: .themed::selected:focus on top
        $widget->setFocused(true);
        $style = $stylesheet->resolveElement($widget, 'selected');
        $this->assertSame(Color::named('green')->toForegroundCode(), $style->getColor()->toForegroundCode());
    }

    public function testResolveElementDoesNotAffectWidgetResolve()
    {
        // Sub-element rules should NOT bleed into widget-level resolve()
        $stylesheet = new StyleSheet([
            TextWidget::class => new Style()->withColor('red'),
            TextWidget::class.'::heading' => new Style()->withBold()->withColor('cyan'),
        ]);

        $widget = new TextWidget('Hello');

        // Widget-level resolve should only get red color, not bold
        $widgetStyle = $stylesheet->resolve($widget);
        $this->assertSame(Color::named('red')->toForegroundCode(), $widgetStyle->getColor()->toForegroundCode());
        $this->assertNull($widgetStyle->getBold());

        // Element-level should get bold + cyan
        $elementStyle = $stylesheet->resolveElement($widget, 'heading');
        $this->assertTrue($elementStyle->getBold());
        $this->assertSame(Color::named('cyan')->toForegroundCode(), $elementStyle->getColor()->toForegroundCode());
    }

    // --- Responsive Breakpoint Tests ---

    public function testBreakpointAppliesAtOrAboveThreshold()
    {
        $stylesheet = new StyleSheet();
        $stylesheet->addRule('.panes', new Style(direction: Direction::Vertical));
        $stylesheet->addBreakpoint(120, '.panes', new Style(direction: Direction::Horizontal));

        $widget = new ContainerWidget()->addStyleClass('panes');

        // Below threshold: vertical
        $resolved = $stylesheet->resolve($widget, 80);
        $this->assertSame(Direction::Vertical, $resolved->getDirection());

        // At threshold: horizontal
        $resolved = $stylesheet->resolve($widget, 120);
        $this->assertSame(Direction::Horizontal, $resolved->getDirection());

        // Above threshold: horizontal
        $resolved = $stylesheet->resolve($widget, 200);
        $this->assertSame(Direction::Horizontal, $resolved->getDirection());
    }

    public function testBreakpointDoesNotApplyWithoutColumns()
    {
        $stylesheet = new StyleSheet();
        $stylesheet->addRule('.panes', new Style(direction: Direction::Vertical));
        $stylesheet->addBreakpoint(120, '.panes', new Style(direction: Direction::Horizontal));

        $widget = new ContainerWidget()->addStyleClass('panes');

        // Without columns, breakpoints are ignored
        $resolved = $stylesheet->resolve($widget);
        $this->assertSame(Direction::Vertical, $resolved->getDirection());
    }

    public function testMultipleBreakpointsAscendingOrder()
    {
        $stylesheet = new StyleSheet();
        $stylesheet->addRule('.card', new Style(gap: 0));
        $stylesheet->addBreakpoint(80, '.card', new Style(gap: 1));
        $stylesheet->addBreakpoint(120, '.card', new Style(gap: 2));
        $stylesheet->addBreakpoint(160, '.card', new Style(gap: 3));

        $widget = new ContainerWidget()->addStyleClass('card');

        // Below all breakpoints
        $this->assertSame(0, $stylesheet->resolve($widget, 60)->getGap());

        // First breakpoint
        $this->assertSame(1, $stylesheet->resolve($widget, 80)->getGap());

        // Second breakpoint overrides first
        $this->assertSame(2, $stylesheet->resolve($widget, 120)->getGap());

        // Third breakpoint overrides all
        $this->assertSame(3, $stylesheet->resolve($widget, 200)->getGap());
    }

    public function testBreakpointMergesWithBaseRules()
    {
        $stylesheet = new StyleSheet();
        $stylesheet->addRule('.card', new Style(gap: 1)->withBackground('blue'));
        $stylesheet->addBreakpoint(120, '.card', new Style(direction: Direction::Horizontal));

        $widget = new ContainerWidget()->addStyleClass('card');

        // Below breakpoint: base rules only
        $resolved = $stylesheet->resolve($widget, 80);
        $this->assertSame(1, $resolved->getGap());
        $this->assertSame(Color::named('blue')->toForegroundCode(), $resolved->getBackground()->toForegroundCode());
        $this->assertNull($resolved->getDirection());

        // Above breakpoint: breakpoint merges on top (adds direction, keeps gap and background)
        $resolved = $stylesheet->resolve($widget, 120);
        $this->assertSame(1, $resolved->getGap());
        $this->assertSame(Color::named('blue')->toForegroundCode(), $resolved->getBackground()->toForegroundCode());
        $this->assertSame(Direction::Horizontal, $resolved->getDirection());
    }

    public function testBreakpointDoesNotOverrideInstanceStyle()
    {
        $stylesheet = new StyleSheet();
        $stylesheet->addBreakpoint(120, '.card', new Style(gap: 2));

        $widget = new ContainerWidget()->addStyleClass('card');
        $widget->setStyle(new Style(gap: 5));

        // Instance style overrides breakpoint
        $resolved = $stylesheet->resolve($widget, 200);
        $this->assertSame(5, $resolved->getGap());
    }

    public function testBreakpointWithUniversalSelector()
    {
        $stylesheet = new StyleSheet();
        $stylesheet->addBreakpoint(100, '*', new Style()->withColor('red'));

        $widget = new TextWidget('Hello');

        // Below: no color
        $this->assertNull($stylesheet->resolve($widget, 80)->getColor());

        // Above: red
        $this->assertSame(Color::named('red')->toForegroundCode(), $stylesheet->resolve($widget, 100)->getColor()->toForegroundCode());
    }

    public function testBreakpointWithFqcnSelector()
    {
        $stylesheet = new StyleSheet();
        $stylesheet->addBreakpoint(100, ContainerWidget::class, new Style(direction: Direction::Horizontal));

        $widget = new ContainerWidget();

        // Below: default
        $this->assertNull($stylesheet->resolve($widget, 80)->getDirection());

        // Above: horizontal
        $this->assertSame(Direction::Horizontal, $stylesheet->resolve($widget, 100)->getDirection());
    }

    public function testBreakpointWithStateSelector()
    {
        $stylesheet = new StyleSheet();
        $stylesheet->addBreakpoint(100, InputWidget::class.':focus', new Style()->withBold());

        $widget = new InputWidget();

        // Unfocused at any width: no bold
        $this->assertNull($stylesheet->resolve($widget, 200)->getBold());

        // Focused below threshold: no bold
        $widget->setFocused(true);
        $this->assertNull($stylesheet->resolve($widget, 80)->getBold());

        // Focused above threshold: bold
        $this->assertTrue($stylesheet->resolve($widget, 100)->getBold());
    }

    public function testBreakpointMergeWithOtherStylesheet()
    {
        $base = new StyleSheet();
        $base->addBreakpoint(100, '.card', new Style(gap: 1));

        $override = new StyleSheet();
        $override->addBreakpoint(100, '.card', new Style(gap: 2));
        $override->addBreakpoint(150, '.card', new Style(gap: 3));

        $merged = $base->merge($override);

        $widget = new ContainerWidget()->addStyleClass('card');

        // The override's rule for minColumns=100 replaces base's
        $this->assertSame(2, $merged->resolve($widget, 100)->getGap());

        // The override's rule for minColumns=150 is added
        $this->assertSame(3, $merged->resolve($widget, 150)->getGap());
    }

    public function testHiddenMergesCorrectly()
    {
        $stylesheet = new StyleSheet([
            '.base' => new Style(hidden: true),
        ]);

        $widget = new ContainerWidget()->addStyleClass('base');
        $this->assertTrue($stylesheet->resolve($widget)->getHidden());

        // Instance style with hidden=false overrides stylesheet
        $widget->setStyle(new Style(hidden: false));
        $this->assertFalse($stylesheet->resolve($widget)->getHidden());
    }

    public function testHiddenNullDoesNotOverride()
    {
        $stylesheet = new StyleSheet([
            '.base' => new Style(hidden: true),
            '.extra' => new Style(bold: true),
        ]);

        // Widget with both classes; .extra has hidden=null, should not override .base's hidden=true
        $widget = new ContainerWidget()->addStyleClass('base')->addStyleClass('extra');
        $this->assertTrue($stylesheet->resolve($widget)->getHidden());
        $this->assertTrue($stylesheet->resolve($widget)->getBold());
    }

    public function testMergeFontInheritance()
    {
        // Base rule sets font, override rule doesn't -> should inherit
        $stylesheet = new StyleSheet()
            ->addRule('*', new Style(font: 'big'))
            ->addRule(TextWidget::class, new Style()->withColor('red'));
        $widget = new TextWidget('Hello');

        $resolved = $stylesheet->resolve($widget);

        $this->assertSame('big', $resolved->getFont());
        $this->assertSame(Color::named('red')->toForegroundCode(), $resolved->getColor()->toForegroundCode());
    }

    public function testMergeFontOverride()
    {
        // Base rule sets font, override rule sets different font -> should override
        $stylesheet = new StyleSheet()
            ->addRule('*', new Style(font: 'big'))
            ->addRule(TextWidget::class, new Style(font: 'small'));
        $widget = new TextWidget('Hello');

        $resolved = $stylesheet->resolve($widget);

        $this->assertSame('small', $resolved->getFont());
    }

    public function testFontFromClassSelector()
    {
        $stylesheet = new StyleSheet()
            ->addRule('.title', new Style(font: 'slant'));

        $widget = new TextWidget('Hello')
            ->addStyleClass('title');

        $resolved = $stylesheet->resolve($widget);

        $this->assertSame('slant', $resolved->getFont());
    }

    public function testInstanceStyleFontOverridesStylesheet()
    {
        $stylesheet = new StyleSheet()
            ->addRule('*', new Style(font: 'big'));

        $widget = new TextWidget('Hello');
        $widget->setStyle(new Style(font: 'mini'));

        $resolved = $stylesheet->resolve($widget);

        $this->assertSame('mini', $resolved->getFont());
    }

    public function testSubclassOverriddenMergeStylesIsCalledByResolve()
    {
        $stylesheet = new AlwaysDimStyleSheet([
            TextWidget::class => new Style(bold: true),
        ]);

        $widget = new TextWidget('Hello');
        $resolved = $stylesheet->resolve($widget);

        // AlwaysDimStyleSheet::mergeStyles() forces dim=true
        $this->assertTrue($resolved->getDim(), 'resolve() should call overridden mergeStyles() via static::');
    }

    public function testSubclassOverriddenMergeStylesIsCalledByResolveElement()
    {
        $stylesheet = new AlwaysDimStyleSheet([
            TextWidget::class.'::label' => new Style(bold: true),
        ]);

        $widget = new TextWidget('Hello');
        $resolved = $stylesheet->resolveElement($widget, 'label');

        // AlwaysDimStyleSheet::mergeStyles() forces dim=true
        $this->assertTrue($resolved->getDim(), 'resolveElement() should call overridden mergeStyles() via static::');
    }
}

/**
 * @internal
 */
class AlwaysDimStyleSheet extends StyleSheet
{
    protected static function mergeStyles(array $styles): Style
    {
        $result = parent::mergeStyles($styles);

        return new Style(
            padding: $result->getPadding(),
            border: $result->getBorder(),
            background: $result->getBackground(),
            color: $result->getColor(),
            bold: $result->getBold(),
            dim: true,
            italic: $result->getItalic(),
        );
    }
}
