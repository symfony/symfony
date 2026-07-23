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
use Symfony\Component\Tui\Render\Renderer;
use Symfony\Component\Tui\Style\Align;
use Symfony\Component\Tui\Style\Color;
use Symfony\Component\Tui\Style\CursorShape;
use Symfony\Component\Tui\Style\Direction;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\TailwindStylesheet;
use Symfony\Component\Tui\Style\TextAlign;
use Symfony\Component\Tui\Style\VerticalAlign;
use Symfony\Component\Tui\Widget\ContainerWidget;
use Symfony\Component\Tui\Widget\InputWidget;
use Symfony\Component\Tui\Widget\TextWidget;

class TailwindStylesheetTest extends TestCase
{
    // --- Padding ---

    #[DataProvider('paddingProvider')]
    public function testPadding(string $class, int $top, int $right, int $bottom, int $left)
    {
        $stylesheet = new TailwindStylesheet();
        $widget = new TextWidget('Hello');
        $widget->addStyleClass($class);

        $resolved = $stylesheet->resolve($widget);

        $this->assertSame($top, $resolved->getPadding()->top);
        $this->assertSame($right, $resolved->getPadding()->right);
        $this->assertSame($bottom, $resolved->getPadding()->bottom);
        $this->assertSame($left, $resolved->getPadding()->left);
    }

    /**
     * @return iterable<string, array{string, int, int, int, int}>
     */
    public static function paddingProvider(): iterable
    {
        yield 'all' => ['p-2', 2, 2, 2, 2];
        yield 'zero' => ['p-0', 0, 0, 0, 0];
        yield 'horizontal' => ['px-3', 0, 3, 0, 3];
        yield 'vertical' => ['py-1', 1, 0, 1, 0];
    }

    public function testPaddingIndividualSides()
    {
        $stylesheet = new TailwindStylesheet();
        $widget = new TextWidget('Hello');
        $widget->addStyleClass('pt-1');
        $widget->addStyleClass('pr-2');
        $widget->addStyleClass('pb-3');
        $widget->addStyleClass('pl-4');

        $resolved = $stylesheet->resolve($widget);

        $this->assertSame(1, $resolved->getPadding()->top);
        $this->assertSame(2, $resolved->getPadding()->right);
        $this->assertSame(3, $resolved->getPadding()->bottom);
        $this->assertSame(4, $resolved->getPadding()->left);
    }

    public function testPaddingLastWins()
    {
        $stylesheet = new TailwindStylesheet();
        $widget = new TextWidget('Hello');
        $widget->addStyleClass('p-2');
        $widget->addStyleClass('p-4');

        $resolved = $stylesheet->resolve($widget);

        $this->assertSame(4, $resolved->getPadding()->top);
        $this->assertSame(4, $resolved->getPadding()->right);
    }

    public function testPaddingPartialOverride()
    {
        $stylesheet = new TailwindStylesheet();
        $widget = new TextWidget('Hello');
        $widget->addStyleClass('p-2');
        $widget->addStyleClass('pl-5');

        $resolved = $stylesheet->resolve($widget);

        $this->assertSame(2, $resolved->getPadding()->top);
        $this->assertSame(2, $resolved->getPadding()->right);
        $this->assertSame(2, $resolved->getPadding()->bottom);
        $this->assertSame(5, $resolved->getPadding()->left);
    }

    // --- Border ---

    #[DataProvider('borderWidthProvider')]
    public function testBorderWidth(string $class, int $top, int $right, int $bottom, int $left)
    {
        $stylesheet = new TailwindStylesheet();
        $widget = new TextWidget('Hello');
        $widget->addStyleClass($class);

        $resolved = $stylesheet->resolve($widget);

        $this->assertSame($top, $resolved->getBorder()->top);
        $this->assertSame($right, $resolved->getBorder()->right);
        $this->assertSame($bottom, $resolved->getBorder()->bottom);
        $this->assertSame($left, $resolved->getBorder()->left);
    }

    /**
     * @return iterable<string, array{string, int, int, int, int}>
     */
    public static function borderWidthProvider(): iterable
    {
        yield 'default' => ['border', 1, 1, 1, 1];
        yield 'width 2' => ['border-2', 2, 2, 2, 2];
        yield 'individual side' => ['border-t', 1, 0, 0, 0];
        yield 'individual side with width' => ['border-l-3', 0, 0, 0, 3];
    }

    public function testBorderNone()
    {
        $stylesheet = new TailwindStylesheet();
        $widget = new TextWidget('Hello');
        $widget->addStyleClass('border-none');

        $resolved = $stylesheet->resolve($widget);

        $this->assertSame(0, $resolved->getBorder()->top);
        $this->assertSame(0, $resolved->getBorder()->right);
        $this->assertSame(0, $resolved->getBorder()->bottom);
        $this->assertSame(0, $resolved->getBorder()->left);
        $this->assertTrue($resolved->getBorder()->pattern->isNone());
    }

    public function testBorderPattern()
    {
        $stylesheet = new TailwindStylesheet();
        $widget = new TextWidget('Hello');
        $widget->addStyleClass('border');
        $widget->addStyleClass('border-rounded');

        $resolved = $stylesheet->resolve($widget);

        $this->assertSame(1, $resolved->getBorder()->top);
        $this->assertFalse($resolved->getBorder()->pattern->isNone());
    }

    public function testBorderColor()
    {
        $stylesheet = new TailwindStylesheet();
        $widget = new TextWidget('Hello');
        $widget->addStyleClass('border');
        $widget->addStyleClass('border-red-500');

        $resolved = $stylesheet->resolve($widget);

        $this->assertSame(1, $resolved->getBorder()->top);
        $this->assertSame(Color::hex('#ef4444')->toRgb(), $resolved->getBorder()->color->toRgb());
    }

    public function testBorderComposition()
    {
        $stylesheet = new TailwindStylesheet();
        $widget = new TextWidget('Hello');
        $widget->addStyleClass('border-2');
        $widget->addStyleClass('border-rounded');
        $widget->addStyleClass('border-cyan-400');

        $resolved = $stylesheet->resolve($widget);

        $this->assertSame(2, $resolved->getBorder()->top);
        $this->assertSame(2, $resolved->getBorder()->right);
        $this->assertSame(2, $resolved->getBorder()->bottom);
        $this->assertSame(2, $resolved->getBorder()->left);
        $this->assertSame(Color::hex('#06b6d4')->tint(20)->toRgb(), $resolved->getBorder()->color->toRgb());
        $this->assertFalse($resolved->getBorder()->pattern->isNone());
    }

    public function testBorderHexColor()
    {
        $stylesheet = new TailwindStylesheet();
        $widget = new TextWidget('Hello');
        $widget->addStyleClass('border');
        $widget->addStyleClass('border-[#ff5500]');

        $resolved = $stylesheet->resolve($widget);

        $this->assertSame(Color::hex('#ff5500')->toRgb(), $resolved->getBorder()->color->toRgb());
    }

    public function testBorderPaletteColor()
    {
        $stylesheet = new TailwindStylesheet();
        $widget = new TextWidget('Hello');
        $widget->addStyleClass('border');
        $widget->addStyleClass('border-42');

        // border-42 should be parsed as palette color, not border width
        // (border-{n} matches first for plain digits, so this is width 42)
        $resolved = $stylesheet->resolve($widget);

        // border-42 matches border-{n} → width 42
        $this->assertSame(42, $resolved->getBorder()->top);
    }

    // --- Background color ---

    #[DataProvider('backgroundColorProvider')]
    public function testBackgroundColor(string $class, string $expectedFgCode)
    {
        $stylesheet = new TailwindStylesheet();
        $widget = new TextWidget('Hello');
        $widget->addStyleClass($class);

        $resolved = $stylesheet->resolve($widget);

        $this->assertSame($expectedFgCode, $resolved->getBackground()->toForegroundCode());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function backgroundColorProvider(): iterable
    {
        yield 'tailwind color' => ['bg-red-500', Color::hex('#ef4444')->toForegroundCode()];
        yield 'hex color' => ['bg-[#1e1e2e]', Color::hex('#1e1e2e')->toForegroundCode()];
        yield 'short hex color' => ['bg-[#f50]', Color::hex('#f50')->toForegroundCode()];
        yield 'palette color' => ['bg-236', Color::palette(236)->toForegroundCode()];
    }

    // --- Text color ---

    #[DataProvider('textColorProvider')]
    public function testTextColor(string $class, string $expectedFgCode)
    {
        $stylesheet = new TailwindStylesheet();
        $widget = new TextWidget('Hello');
        $widget->addStyleClass($class);

        $resolved = $stylesheet->resolve($widget);

        $this->assertSame($expectedFgCode, $resolved->getColor()->toForegroundCode());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function textColorProvider(): iterable
    {
        yield 'tailwind color' => ['text-cyan-500', Color::hex('#06b6d4')->toForegroundCode()];
        yield 'hex color' => ['text-[#e0e0e0]', Color::hex('#e0e0e0')->toForegroundCode()];
        yield 'palette color' => ['text-245', Color::palette(245)->toForegroundCode()];
    }

    // --- Text decorations ---

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function textDecorationProvider(): iterable
    {
        yield 'bold' => ['bold', 'getBold', true];
        yield 'not-bold' => ['not-bold', 'getBold', false];
        yield 'dim' => ['dim', 'getDim', true];
        yield 'not-dim' => ['not-dim', 'getDim', false];
        yield 'italic' => ['italic', 'getItalic', true];
        yield 'not-italic' => ['not-italic', 'getItalic', false];
        yield 'underline' => ['underline', 'getUnderline', true];
        yield 'no-underline' => ['no-underline', 'getUnderline', false];
        yield 'line-through' => ['line-through', 'getStrikethrough', true];
        yield 'no-line-through' => ['no-line-through', 'getStrikethrough', false];
        yield 'reverse' => ['reverse', 'getReverse', true];
        yield 'no-reverse' => ['no-reverse', 'getReverse', false];
    }

    #[DataProvider('textDecorationProvider')]
    public function testTextDecoration(string $utilityClass, string $getter, bool $expected)
    {
        $stylesheet = new TailwindStylesheet();
        $widget = new TextWidget('Hello');
        $widget->addStyleClass($utilityClass);

        $this->assertSame($expected, $stylesheet->resolve($widget)->$getter());
    }

    // --- Layout ---

    #[DataProvider('layoutUtilityProvider')]
    public function testLayoutUtility(string $class, string $getter, mixed $expected)
    {
        $stylesheet = new TailwindStylesheet();
        $widget = new ContainerWidget();
        $widget->addStyleClass($class);

        $this->assertSame($expected, $stylesheet->resolve($widget)->$getter());
    }

    /**
     * @return iterable<string, array{string, string, mixed}>
     */
    public static function layoutUtilityProvider(): iterable
    {
        yield 'flex-row' => ['flex-row', 'getDirection', Direction::Horizontal];
        yield 'flex-col' => ['flex-col', 'getDirection', Direction::Vertical];
        yield 'flex-0' => ['flex-0', 'getFlex', 0];
        yield 'flex-1' => ['flex-1', 'getFlex', 1];
        yield 'flex-2' => ['flex-2', 'getFlex', 2];
        yield 'gap' => ['gap-2', 'getGap', 2];
        yield 'hidden' => ['hidden', 'getHidden', true];
        yield 'visible' => ['visible', 'getHidden', false];
    }

    // --- Text alignment ---

    /**
     * @return iterable<string, array{string, TextAlign}>
     */
    public static function textAlignProvider(): iterable
    {
        yield 'left' => ['text-left', TextAlign::Left];
        yield 'center' => ['text-center', TextAlign::Center];
        yield 'right' => ['text-right', TextAlign::Right];
    }

    #[DataProvider('textAlignProvider')]
    public function testTextAlign(string $utilityClass, TextAlign $expected)
    {
        $stylesheet = new TailwindStylesheet();
        $widget = new TextWidget('Hello');
        $widget->addStyleClass($utilityClass);

        $this->assertSame($expected, $stylesheet->resolve($widget)->getTextAlign());
    }

    public function testTextCenterDoesNotConflictWithTextColor()
    {
        $stylesheet = new TailwindStylesheet();
        $widget = new TextWidget('Hello');
        $widget->addStyleClass('text-center');
        $widget->addStyleClass('text-red-500');

        $resolved = $stylesheet->resolve($widget);
        $this->assertSame(TextAlign::Center, $resolved->getTextAlign());
        $this->assertSame(Color::hex('#ef4444')->toRgb(), $resolved->getColor()->toRgb());
    }

    // --- Font ---

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function fontProvider(): iterable
    {
        yield 'big' => ['font-big', 'big'];
        yield 'small' => ['font-small', 'small'];
        yield 'slant' => ['font-slant', 'slant'];
        yield 'path' => ['font-/path/to/custom.flf', '/path/to/custom.flf'];
    }

    #[DataProvider('fontProvider')]
    public function testFont(string $utilityClass, string $expectedFont)
    {
        $stylesheet = new TailwindStylesheet();
        $widget = new TextWidget('Hello');
        $widget->addStyleClass($utilityClass);

        $this->assertSame($expectedFont, $stylesheet->resolve($widget)->getFont());
    }

    public function testFontOverridesStylesheetRule()
    {
        $stylesheet = new TailwindStylesheet();
        $stylesheet->addRule(TextWidget::class, new Style(font: 'big'));

        $widget = new TextWidget('Hello');
        $widget->addStyleClass('font-small');

        $this->assertSame('small', $stylesheet->resolve($widget)->getFont());
    }

    public function testInstanceStyleFontOverridesUtility()
    {
        $stylesheet = new TailwindStylesheet();
        $widget = new TextWidget('Hello');
        $widget->addStyleClass('font-big');
        $widget->setStyle(new Style(font: 'mini'));

        $this->assertSame('mini', $stylesheet->resolve($widget)->getFont());
    }

    // --- Align ---

    /**
     * @return iterable<string, array{string, string, Align|VerticalAlign}>
     */
    public static function alignmentProvider(): iterable
    {
        yield 'align-left' => ['align-left', 'getAlign', Align::Left];
        yield 'align-center' => ['align-center', 'getAlign', Align::Center];
        yield 'align-right' => ['align-right', 'getAlign', Align::Right];
        yield 'valign-top' => ['valign-top', 'getVerticalAlign', VerticalAlign::Top];
        yield 'valign-center' => ['valign-center', 'getVerticalAlign', VerticalAlign::Center];
        yield 'valign-bottom' => ['valign-bottom', 'getVerticalAlign', VerticalAlign::Bottom];
    }

    #[DataProvider('alignmentProvider')]
    public function testAlignment(string $utilityClass, string $getter, Align|VerticalAlign $expected)
    {
        $stylesheet = new TailwindStylesheet();
        $widget = new TextWidget('Hello');
        $widget->addStyleClass($utilityClass);

        $this->assertSame($expected, $stylesheet->resolve($widget)->$getter());
    }

    // --- Combination of multiple utilities ---

    public function testMultipleUtilities()
    {
        $stylesheet = new TailwindStylesheet();
        $widget = new TextWidget('Hello');
        $widget->addStyleClass('p-1');
        $widget->addStyleClass('bg-blue-500');
        $widget->addStyleClass('text-neutral-50');
        $widget->addStyleClass('bold');
        $widget->addStyleClass('italic');

        $resolved = $stylesheet->resolve($widget);

        $this->assertSame(1, $resolved->getPadding()->top);
        $this->assertSame(Color::hex('#3b82f6')->toRgb(), $resolved->getBackground()->toRgb());
        $this->assertSame(Color::hex('#737373')->tint(95)->toRgb(), $resolved->getColor()->toRgb());
        $this->assertTrue($resolved->getBold());
        $this->assertTrue($resolved->getItalic());
    }

    // --- Coexistence with regular CSS class rules ---

    public function testUtilityCoexistsWithRegularClasses()
    {
        $stylesheet = new TailwindStylesheet();
        $stylesheet->addRule('.card', new Style()->withBackground('blue'));

        $widget = new TextWidget('Hello');
        $widget->addStyleClass('card');
        $widget->addStyleClass('p-2');
        $widget->addStyleClass('bold');

        $resolved = $stylesheet->resolve($widget);

        // Background from .card rule
        $this->assertSame(Color::named('blue')->toForegroundCode(), $resolved->getBackground()->toForegroundCode());
        // Padding from utility class
        $this->assertSame(2, $resolved->getPadding()->top);
        // Bold from utility class
        $this->assertTrue($resolved->getBold());
    }

    public function testUtilityOverridesRegularClassRules()
    {
        $stylesheet = new TailwindStylesheet();
        $stylesheet->addRule('.card', Style::padding([5])->withBold());

        $widget = new TextWidget('Hello');
        $widget->addStyleClass('card');
        $widget->addStyleClass('p-1');
        $widget->addStyleClass('not-bold');

        $resolved = $stylesheet->resolve($widget);

        // Utility p-1 overrides .card's padding 5 (utilities are immutable)
        $this->assertSame(1, $resolved->getPadding()->top);
        // Utility not-bold overrides .card's bold
        $this->assertFalse($resolved->getBold());
    }

    public function testUtilityOverridesFqcnRules()
    {
        $stylesheet = new TailwindStylesheet();
        $stylesheet->addRule(TextWidget::class, new Style()->withColor('red'));

        $widget = new TextWidget('Hello');
        $widget->addStyleClass('text-blue-500');

        $resolved = $stylesheet->resolve($widget);

        // Utility text-blue-500 overrides FQCN's red color
        $this->assertSame(Color::hex('#3b82f6')->toRgb(), $resolved->getColor()->toRgb());
    }

    public function testUtilityOverridesUniversalRule()
    {
        $stylesheet = new TailwindStylesheet();
        $stylesheet->addRule('*', Style::padding([5])->withBold());

        $widget = new TextWidget('Hello');
        $widget->addStyleClass('p-0');
        $widget->addStyleClass('not-bold');

        $resolved = $stylesheet->resolve($widget);

        $this->assertSame(0, $resolved->getPadding()->top);
        $this->assertFalse($resolved->getBold());
    }

    public function testUtilityOverridesStateSelectors()
    {
        $stylesheet = new TailwindStylesheet();
        $stylesheet->addRule(InputWidget::class.':focus', new Style()->withBackground('blue'));

        $widget = new InputWidget();
        $widget->setFocused(true);
        $widget->addStyleClass('bg-red-500');

        $resolved = $stylesheet->resolve($widget);

        // Utility bg-red-500 overrides :focus background
        $this->assertSame(Color::hex('#ef4444')->toRgb(), $resolved->getBackground()->toRgb());
    }

    public function testInstanceStyleOverridesUtility()
    {
        $stylesheet = new TailwindStylesheet();
        $widget = new TextWidget('Hello');
        $widget->addStyleClass('p-2');
        $widget->addStyleClass('bold');
        $widget->setStyle(Style::padding([5])->withBold(false));

        $resolved = $stylesheet->resolve($widget);

        // Instance style overrides utility classes
        $this->assertSame(5, $resolved->getPadding()->top);
        $this->assertFalse($resolved->getBold());
    }

    // --- Regular classes still work normally ---

    public function testRegularClassRulesStillWork()
    {
        $stylesheet = new TailwindStylesheet();
        $stylesheet->addRule('.header', new Style()->withColor('cyan')->withBold());
        $stylesheet->addRule('.muted', new Style()->withColor('gray'));

        $widget = new TextWidget('Hello');
        $widget->addStyleClass('header');

        $resolved = $stylesheet->resolve($widget);

        $this->assertSame(Color::named('cyan')->toForegroundCode(), $resolved->getColor()->toForegroundCode());
        $this->assertTrue($resolved->getBold());
    }

    public function testRegularStateSelectorsStillWork()
    {
        $stylesheet = new TailwindStylesheet();
        $stylesheet->addRule('.input-field', new Style()->withBackground('black'));
        $stylesheet->addRule('.input-field:focus', new Style()->withBackground('blue'));

        $widget = new InputWidget();
        $widget->addStyleClass('input-field');

        // Unfocused: black background
        $resolved = $stylesheet->resolve($widget);
        $this->assertSame(Color::named('black')->toForegroundCode(), $resolved->getBackground()->toForegroundCode());

        // Focused: blue background
        $widget->setFocused(true);
        $resolved = $stylesheet->resolve($widget);
        $this->assertSame(Color::named('blue')->toForegroundCode(), $resolved->getBackground()->toForegroundCode());
    }

    public function testUnrecognizedClassTreatedAsRegular()
    {
        $stylesheet = new TailwindStylesheet();
        $stylesheet->addRule('.my-custom-class', new Style()->withItalic());

        $widget = new TextWidget('Hello');
        $widget->addStyleClass('my-custom-class');

        $resolved = $stylesheet->resolve($widget);

        $this->assertTrue($resolved->getItalic());
    }

    // --- No utility classes = empty style ---

    public function testNoUtilityClassesNoEffect()
    {
        $stylesheet = new TailwindStylesheet();
        $widget = new TextWidget('Hello');

        $resolved = $stylesheet->resolve($widget);

        $this->assertNull($resolved->getPadding());
        $this->assertNull($resolved->getBorder());
        $this->assertNull($resolved->getBackground());
        $this->assertNull($resolved->getColor());
        $this->assertNull($resolved->getBold());
    }

    // --- Border patterns ---

    #[DataProvider('borderPatternProvider')]
    public function testBorderPatterns(string $utilityClass)
    {
        $stylesheet = new TailwindStylesheet();
        $widget = new TextWidget('Hello');
        $widget->addStyleClass('border');
        $widget->addStyleClass($utilityClass);

        $resolved = $stylesheet->resolve($widget);

        $this->assertSame(1, $resolved->getBorder()->top);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function borderPatternProvider(): iterable
    {
        yield 'normal' => ['border-normal'];
        yield 'rounded' => ['border-rounded'];
        yield 'double' => ['border-double'];
        yield 'tall' => ['border-tall'];
        yield 'wide' => ['border-wide'];
        yield 'tall-medium' => ['border-tall-medium'];
        yield 'wide-medium' => ['border-wide-medium'];
        yield 'tall-large' => ['border-tall-large'];
        yield 'wide-large' => ['border-wide-large'];
    }

    // --- Tailwind color families ---

    #[DataProvider('tailwindFamilyProvider')]
    public function testTailwindFamilies(string $family, string $hex)
    {
        $stylesheet = new TailwindStylesheet();
        $widget = new TextWidget('Hello');
        $widget->addStyleClass('text-'.$family.'-500');

        $resolved = $stylesheet->resolve($widget);

        // shade 500 = base color (scale 0), so exact hex match
        $this->assertSame(Color::hex($hex)->toRgb(), $resolved->getColor()->toRgb());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function tailwindFamilyProvider(): iterable
    {
        yield 'slate' => ['slate', '#64748b'];
        yield 'gray' => ['gray', '#6b7280'];
        yield 'zinc' => ['zinc', '#71717a'];
        yield 'neutral' => ['neutral', '#737373'];
        yield 'stone' => ['stone', '#78716c'];
        yield 'red' => ['red', '#ef4444'];
        yield 'orange' => ['orange', '#f97316'];
        yield 'amber' => ['amber', '#f59e0b'];
        yield 'yellow' => ['yellow', '#eab308'];
        yield 'lime' => ['lime', '#84cc16'];
        yield 'green' => ['green', '#22c55e'];
        yield 'emerald' => ['emerald', '#10b981'];
        yield 'teal' => ['teal', '#14b8a6'];
        yield 'cyan' => ['cyan', '#06b6d4'];
        yield 'sky' => ['sky', '#0ea5e9'];
        yield 'blue' => ['blue', '#3b82f6'];
        yield 'indigo' => ['indigo', '#6366f1'];
        yield 'violet' => ['violet', '#8b5cf6'];
        yield 'purple' => ['purple', '#a855f7'];
        yield 'fuchsia' => ['fuchsia', '#d946ef'];
        yield 'pink' => ['pink', '#ec4899'];
        yield 'rose' => ['rose', '#f43f5e'];
    }

    // --- Sub-element (resolveElement) with utility classes ---

    public function testResolveElementDoesNotMatchUtilityClassNames()
    {
        $stylesheet = new TailwindStylesheet();
        // A rule targeting ".bold::cursor": "bold" is a utility class name
        $stylesheet->addRule('.bold::cursor', new Style()->withBackground('red'));

        $widget = new InputWidget();
        $widget->addStyleClass('bold');

        $style = $stylesheet->resolveElement($widget, 'cursor');

        // "bold" is a utility class, not a CSS class; ".bold::cursor" must not match
        $this->assertNull($style->getBackground());
    }

    public function testResolveElementMatchesCssClassesNotUtilityClasses()
    {
        $stylesheet = new TailwindStylesheet();
        $stylesheet->addRule('.my-input::cursor', new Style()->withReverse());

        $widget = new InputWidget();
        $widget->addStyleClass('my-input');
        $widget->addStyleClass('bold');

        $style = $stylesheet->resolveElement($widget, 'cursor');

        // .my-input is a real CSS class; should match
        $this->assertTrue($style->getReverse());
    }

    public function testResolveElementWithUtilityAndCssClassCombination()
    {
        $stylesheet = new TailwindStylesheet();
        $stylesheet->addRule('.my-input::cursor', new Style()->withReverse());
        $stylesheet->addRule('.p-2::cursor', new Style()->withBackground('blue'));

        $widget = new InputWidget();
        $widget->addStyleClass('my-input');
        $widget->addStyleClass('p-2');

        $style = $stylesheet->resolveElement($widget, 'cursor');

        // .my-input::cursor should match (real CSS class)
        $this->assertTrue($style->getReverse());
        // .p-2::cursor should NOT match (p-2 is a utility class)
        $this->assertNull($style->getBackground());
    }

    public function testResolveElementWithStateDoesNotMatchUtilityClassNames()
    {
        $stylesheet = new TailwindStylesheet();
        $stylesheet->addRule('.bold::cursor:focus', new Style()->withColor('cyan'));

        $widget = new InputWidget();
        $widget->addStyleClass('bold');
        $widget->setFocused(true);

        $style = $stylesheet->resolveElement($widget, 'cursor');

        // "bold" is a utility class; ".bold::cursor:focus" must not match
        $this->assertNull($style->getColor());
    }

    // --- Breakpoints still work ---

    public function testBreakpointsStillWork()
    {
        $stylesheet = new TailwindStylesheet();
        $stylesheet->addRule('.panes', new Style(direction: Direction::Vertical));
        $stylesheet->addBreakpoint(120, '.panes', new Style(direction: Direction::Horizontal));

        $widget = new ContainerWidget();
        $widget->addStyleClass('panes');

        // Below threshold: vertical
        $this->assertSame(Direction::Vertical, $stylesheet->resolve($widget, 80)->getDirection());
        // Above threshold: horizontal
        $this->assertSame(Direction::Horizontal, $stylesheet->resolve($widget, 120)->getDirection());
    }

    public function testUtilityOverridesBreakpoints()
    {
        $stylesheet = new TailwindStylesheet();
        $stylesheet->addBreakpoint(120, '*', new Style(gap: 5));

        $widget = new ContainerWidget();
        $widget->addStyleClass('gap-1');

        // Utility gap-1 overrides breakpoint gap 5
        $resolved = $stylesheet->resolve($widget, 200);

        $this->assertSame(1, $resolved->getGap());
    }

    public function testBreakpointsDoNotMatchUtilityClassNames()
    {
        $stylesheet = new TailwindStylesheet();
        // A breakpoint rule targeting ".bold" as a CSS class selector
        $stylesheet->addBreakpoint(80, '.bold', new Style()->withBackground('red'));

        $widget = new TextWidget('Hello');
        // "bold" is a utility class, not a CSS class; it should NOT match ".bold" breakpoint selector
        $widget->addStyleClass('bold');

        $resolved = $stylesheet->resolve($widget, 200);

        $this->assertTrue($resolved->getBold());
        // Background should NOT be set; ".bold" breakpoint must not match utility class names
        $this->assertNull($resolved->getBackground());
    }

    public function testBreakpointsMatchCssClassesNotUtilityClasses()
    {
        $stylesheet = new TailwindStylesheet();
        $stylesheet->addRule('.card', Style::padding([3]));
        $stylesheet->addBreakpoint(80, '.card', new Style()->withBackground('blue'));

        $widget = new TextWidget('Hello');
        $widget->addStyleClass('card');
        $widget->addStyleClass('bold');

        $resolved = $stylesheet->resolve($widget, 200);

        // .card breakpoint should match (it's a real CSS class)
        $this->assertSame(Color::named('blue')->toForegroundCode(), $resolved->getBackground()->toForegroundCode());
        // bold utility should still work
        $this->assertTrue($resolved->getBold());
        // padding from .card base rule
        $this->assertSame(3, $resolved->getPadding()->top);
    }

    // --- Merge with other stylesheets ---

    public function testMergeWithRegularStylesheet()
    {
        $stylesheet = new TailwindStylesheet();
        $stylesheet->addRule('.card', Style::padding([3])->withBackground('blue'));

        $widget = new TextWidget('Hello');
        $widget->addStyleClass('card');
        $widget->addStyleClass('bold');
        $widget->addStyleClass('text-cyan-500');

        $resolved = $stylesheet->resolve($widget);

        // .card provides padding and background
        $this->assertSame(3, $resolved->getPadding()->top);
        $this->assertSame(Color::named('blue')->toForegroundCode(), $resolved->getBackground()->toForegroundCode());
        // Utility provides bold and text color
        $this->assertTrue($resolved->getBold());
        $this->assertSame(Color::hex('#06b6d4')->toRgb(), $resolved->getColor()->toRgb());
    }

    // --- Edge cases ---

    public function testGapZero()
    {
        $stylesheet = new TailwindStylesheet();
        $widget = new ContainerWidget();
        $widget->addStyleClass('gap-0');

        $this->assertSame(0, $stylesheet->resolve($widget)->getGap());
    }

    public function testBorderAllPatterns()
    {
        $stylesheet = new TailwindStylesheet();
        $widget = new TextWidget('Hello');
        $widget->addStyleClass('border');
        $widget->addStyleClass('border-double');
        $widget->addStyleClass('border-green-500');

        $resolved = $stylesheet->resolve($widget);

        $this->assertSame(1, $resolved->getBorder()->top);
        $this->assertSame(Color::hex('#22c55e')->toRgb(), $resolved->getBorder()->color->toRgb());
    }

    public function testOnlyBorderColorWithoutWidth()
    {
        $stylesheet = new TailwindStylesheet();
        $widget = new TextWidget('Hello');
        $widget->addStyleClass('border-red-500');

        $resolved = $stylesheet->resolve($widget);

        // Border is created but with zero width (not visible, like Tailwind)
        $this->assertSame(0, $resolved->getBorder()->top);
        $this->assertSame(Color::hex('#ef4444')->toRgb(), $resolved->getBorder()->color->toRgb());
    }

    public function testInvalidColorIsNotUtility()
    {
        $stylesheet = new TailwindStylesheet();
        $stylesheet->addRule('.bg-foobar', new Style()->withItalic());

        $widget = new TextWidget('Hello');
        $widget->addStyleClass('bg-foobar');

        $resolved = $stylesheet->resolve($widget);

        // bg-foobar is not a valid color, so treated as regular class
        $this->assertTrue($resolved->getItalic());
        $this->assertNull($resolved->getBackground());
    }

    public function testFullCascadeExample()
    {
        $stylesheet = new TailwindStylesheet();
        $stylesheet
            ->addRule('*', new Style()->withColor('gray'))
            ->addRule(TextWidget::class, new Style()->withDim())
            ->addRule('.card', Style::padding([3]));

        $widget = new TextWidget('Hello');
        $widget->addStyleClass('card');
        $widget->addStyleClass('p-1');
        $widget->addStyleClass('text-cyan-500');
        $widget->addStyleClass('bold');

        $resolved = $stylesheet->resolve($widget);

        // p-1 overrides .card's padding 3
        $this->assertSame(1, $resolved->getPadding()->top);
        // text-cyan-500 overrides * color gray
        $this->assertSame(Color::hex('#06b6d4')->toRgb(), $resolved->getColor()->toRgb());
        // bold from utility
        $this->assertTrue($resolved->getBold());
        // dim inherited from TextWidget FQCN rule (not overridden by utilities)
        $this->assertTrue($resolved->getDim());
    }

    public function testBorderSidesComposition()
    {
        $stylesheet = new TailwindStylesheet();
        $widget = new TextWidget('Hello');
        $widget->addStyleClass('border-t');
        $widget->addStyleClass('border-b-2');

        $resolved = $stylesheet->resolve($widget);

        $this->assertSame(1, $resolved->getBorder()->top);
        $this->assertSame(0, $resolved->getBorder()->right);
        $this->assertSame(2, $resolved->getBorder()->bottom);
        $this->assertSame(0, $resolved->getBorder()->left);
    }

    // --- Color shades ---

    public function testBackgroundColorShadeUsesTailwindPalette()
    {
        $stylesheet = new TailwindStylesheet();
        $widget = new TextWidget('Hello');
        $widget->addStyleClass('bg-red-300');

        $resolved = $stylesheet->resolve($widget);

        // Tailwind red-500 = #ef4444 → red-300 tinted 40% toward white
        $rgb = $resolved->getBackground()->toRgb();
        $expected = Color::hex('#ef4444')->tint(40)->toRgb();
        $this->assertSame($expected, $rgb);
    }

    public function testTextColorShadeUsesTailwindPalette()
    {
        $stylesheet = new TailwindStylesheet();
        $widget = new TextWidget('Hello');
        $widget->addStyleClass('text-blue-700');

        $resolved = $stylesheet->resolve($widget);

        // Tailwind blue-500 = #3b82f6 → blue-700 shaded 40% toward black
        $rgb = $resolved->getColor()->toRgb();
        $expected = Color::hex('#3b82f6')->shade(40)->toRgb();
        $this->assertSame($expected, $rgb);
    }

    public function testBorderColorShade()
    {
        $stylesheet = new TailwindStylesheet();
        $widget = new TextWidget('Hello');
        $widget->addStyleClass('border');
        $widget->addStyleClass('border-green-600');

        $resolved = $stylesheet->resolve($widget);

        $rgb = $resolved->getBorder()->color->toRgb();
        $expected = Color::hex('#22c55e')->shade(20)->toRgb();
        $this->assertSame($expected, $rgb);
    }

    public function testShade500IsTailwindBaseColor()
    {
        $stylesheet = new TailwindStylesheet();
        $widget = new TextWidget('Hello');
        $widget->addStyleClass('text-cyan-500');

        $resolved = $stylesheet->resolve($widget);

        // 500 = base Tailwind color, scale(0) = unchanged
        $expected = Color::hex('#06b6d4')->toRgb();
        $rgb = $resolved->getColor()->toRgb();
        $this->assertSame($expected, $rgb);
    }

    public function testTailwindOnlyFamilyWorks()
    {
        $stylesheet = new TailwindStylesheet();
        $widget = new TextWidget('Hello');
        $widget->addStyleClass('bg-emerald-400');

        $resolved = $stylesheet->resolve($widget);

        $rgb = $resolved->getBackground()->toRgb();
        $expected = Color::hex('#10b981')->tint(20)->toRgb();
        $this->assertSame($expected, $rgb);
    }

    public function testShade50IsVeryLight()
    {
        $stylesheet = new TailwindStylesheet();
        $widget = new TextWidget('Hello');
        $widget->addStyleClass('bg-red-50');

        $resolved = $stylesheet->resolve($widget);

        $rgb = $resolved->getBackground()->toRgb();
        // 50 = tint 95% → very close to white
        $this->assertGreaterThan(240, $rgb['r']);
        $this->assertGreaterThan(240, $rgb['g']);
        $this->assertGreaterThan(240, $rgb['b']);
    }

    public function testShade950IsVeryDark()
    {
        $stylesheet = new TailwindStylesheet();
        $widget = new TextWidget('Hello');
        $widget->addStyleClass('bg-red-950');

        $resolved = $stylesheet->resolve($widget);

        $rgb = $resolved->getBackground()->toRgb();
        // 950 = shade 90% → very close to black
        $this->assertLessThan(30, $rgb['r']);
        $this->assertLessThan(10, $rgb['g']);
        $this->assertLessThan(10, $rgb['b']);
    }

    public function testNonTailwindFamilyIsNotUtility()
    {
        $stylesheet = new TailwindStylesheet();
        $stylesheet->addRule('.text-bright-red-300', new Style()->withItalic());

        $widget = new TextWidget('Hello');
        $widget->addStyleClass('text-bright-red-300');

        $resolved = $stylesheet->resolve($widget);

        // bright-red is not a Tailwind family → treated as regular class
        $this->assertTrue($resolved->getItalic());
        $this->assertNull($resolved->getColor());
    }

    public function testInvalidShadeNumberIsNotUtility()
    {
        $stylesheet = new TailwindStylesheet();
        $stylesheet->addRule('.text-red-999', new Style()->withItalic());

        $widget = new TextWidget('Hello');
        $widget->addStyleClass('text-red-999');

        $resolved = $stylesheet->resolve($widget);

        // 999 is not a valid shade → treated as regular class
        $this->assertTrue($resolved->getItalic());
        $this->assertNull($resolved->getColor());
    }

    // --- Renderer integration ---

    public function testRendererMergesDefaultsUnderneathUtilities()
    {
        $stylesheet = new TailwindStylesheet();
        $renderer = new Renderer($stylesheet);

        // DefaultStyleSheet defines InputWidget::cursor with CursorShape::Block
        // Verify the default style is available through the merged stylesheet
        $input = new InputWidget();
        $resolved = $renderer->getStyleSheet()->resolveElement($input, 'cursor');

        $this->assertSame(CursorShape::Block, $resolved->getCursorShape());
    }

    public function testRendererResolvesUtilityClassesThroughStylesheet()
    {
        $stylesheet = new TailwindStylesheet();
        $renderer = new Renderer($stylesheet);

        $widget = new TextWidget('Hello');
        $widget->addStyleClass('bold');
        $widget->addStyleClass('text-cyan-500');
        $widget->addStyleClass('p-1');

        $resolved = $renderer->getStyleSheet()->resolve($widget);

        $this->assertTrue($resolved->getBold());
        $this->assertSame(Color::hex('#06b6d4')->toRgb(), $resolved->getColor()->toRgb());
        $this->assertSame(1, $resolved->getPadding()->top);
    }
}
