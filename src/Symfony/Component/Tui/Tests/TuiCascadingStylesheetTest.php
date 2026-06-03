<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Render\Renderer;
use Symfony\Component\Tui\Style\Color;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Style\StyleSheet;
use Symfony\Component\Tui\Widget\TextWidget;

/**
 * Tests for cascading stylesheets integration with Tui.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TuiCascadingStylesheetTest extends TestCase
{
    public function testWithoutUserStylesheet()
    {
        // When no stylesheets are provided, defaults are used
        $renderer = new Renderer();

        $stylesheet = $renderer->getStyleSheet();

        // Default stylesheet should resolve a basic widget
        $widget = new TextWidget('test');
        $style = $stylesheet->resolve($widget);
        // Style should have some defaults from the stylesheet
        $this->assertInstanceOf(Style::class, $style);
    }

    public function testWithUserStylesheet()
    {
        // When user stylesheets are provided, they are merged on top of defaults
        $userSheet = new StyleSheet()
            ->addRule('*', new Style()->withColor('blue'));

        $renderer = new Renderer($userSheet);

        $widget = new TextWidget('test');
        $style = $renderer->getStyleSheet()->resolve($widget);

        // The user's universal color rule is applied
        $this->assertSame(Color::named('blue')->toForegroundCode(), $style->getColor()->toForegroundCode());
    }

    public function testUserStylesheetCanOverrideDefaults()
    {
        // User can override default styles for the same selector
        $userSheet = new StyleSheet()
            ->addRule(TextWidget::class, new Style()->withColor('red'));

        $renderer = new Renderer($userSheet);

        $widget = new TextWidget('test');
        $style = $renderer->getStyleSheet()->resolve($widget);

        $this->assertSame(Color::named('red')->toForegroundCode(), $style->getColor()->toForegroundCode());
    }

    public function testUserStylesheetAddsNewRules()
    {
        // User can add rules for selectors not in defaults
        $userSheet = new StyleSheet()
            ->addRule('.custom-widget', new Style()->withColor('yellow'));

        $renderer = new Renderer($userSheet);

        // Custom rule is available
        $widget = new TextWidget('test');
        $widget->addStyleClass('custom-widget');
        $customStyle = $renderer->getStyleSheet()->resolve($widget);
        $this->assertSame(Color::named('yellow')->toForegroundCode(), $customStyle->getColor()->toForegroundCode());
    }

    public function testAddStyleSheetAfterConstruction()
    {
        // addStyleSheet merges user rules on top of defaults
        $renderer = new Renderer();

        $newSheet = new StyleSheet()
            ->addRule('*', new Style()->withColor('green'));

        $renderer->addStyleSheet($newSheet);

        $widget = new TextWidget('test');
        $style = $renderer->getStyleSheet()->resolve($widget);

        // Universal color rule applies
        $this->assertSame(Color::named('green')->toForegroundCode(), $style->getColor()->toForegroundCode());
    }

    public function testMultipleUserStylesheets()
    {
        // Multiple user stylesheets are merged in order (last wins)

        // Sheet 1: application theme
        $themeSheet = new StyleSheet()
            ->addRule('*', new Style()->withColor('red'))
            ->addRule('.header', new Style()->withBold());

        // Sheet 2: user preferences (overrides theme's universal)
        $userSheet = new StyleSheet()
            ->addRule('*', new Style()->withColor('blue'));

        $renderer = new Renderer($themeSheet);
        $renderer->addStyleSheet($userSheet);

        $widget = new TextWidget('test');
        $style = $renderer->getStyleSheet()->resolve($widget);

        // Universal rule from last sheet wins
        $this->assertSame(Color::named('blue')->toForegroundCode(), $style->getColor()->toForegroundCode());

        // .header from theme sheet is still available (different selector)
        $headerWidget = new TextWidget('test');
        $headerWidget->addStyleClass('header');
        $headerStyle = $renderer->getStyleSheet()->resolve($headerWidget);
        $this->assertTrue($headerStyle->getBold());
    }
}
