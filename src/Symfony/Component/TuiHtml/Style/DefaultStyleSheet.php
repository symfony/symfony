<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Style;

use Symfony\Component\Tui\Widget\CancellableLoaderWidget;
use Symfony\Component\Tui\Widget\KeyBindingWidget;
use Symfony\Component\Tui\Widget\MarkdownWidget;
use Symfony\Component\Tui\Widget\SettingsListWidget;
use Symfony\Component\Tui\Widget\TabsWidget;
use Symfony\Component\TuiHtml\Widget\CollapsibleWidget;
use Symfony\Component\TuiHtml\Widget\EditorWidget;
use Symfony\Component\TuiHtml\Widget\InputWidget;
use Symfony\Component\TuiHtml\Widget\LoaderWidget;
use Symfony\Component\TuiHtml\Widget\SelectListWidget;

/**
 * Default TUI stylesheet with base styling rules.
 *
 * Provides sensible defaults for all core widget sub-elements.
 * These can be overridden by application or theme stylesheets
 * via the cascade mechanism.
 *
 * @experimental
 *
 * @internal
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class DefaultStyleSheet
{
    public static function create(): StyleSheet
    {
        return new StyleSheet([
            // Heading classes (used by <h1>–<h6> tag aliases)
            '.h1' => new Style(null, null, null, 'cyan', true),
            '.h2' => new Style(null, null, null, 'blue', true),
            '.h3' => new Style(null, null, null, null, true),
            '.h4' => new Style(null, null, null, null, true, true),
            '.h5' => new Style(null, null, null, null, null, true),
            '.h6' => new Style(null, null, null, null, null, true, true),
            '.hr' => new Style(null, null, null, 'gray'),
            '.p' => new Style(),
            // Layout aliases (used by <columns>/<column> tag aliases)
            '.columns' => new Style(null, null, null, null, null, null, null, null, null, null, Direction::Horizontal, 2),
            '.column' => new Style(),
            // KeyBindingWidget
            KeyBindingWidget::class => new Style(Padding::from([0, 1]), Border::all(1)),
            KeyBindingWidget::class.'::key' => new Style(null, null, 'gray', null, true),
            KeyBindingWidget::class.'::action' => new Style(null, null, null, 'gray'),
            KeyBindingWidget::class.'::global-key' => new Style(null, null, 'blue', null, true),
            KeyBindingWidget::class.'::global-action' => new Style(null, null, null, 'gray'),
            // CancellableLoaderWidget
            CancellableLoaderWidget::class.':focus' => (new Style())->withBold(),
            // CollapsibleWidget
            CollapsibleWidget::class.'::symbol:focus' => (new Style())->withReverse(),
            CollapsibleWidget::class.'::summary:focus' => (new Style())->withReverse(),
            CollapsibleWidget::class.'::description' => (new Style())->withColor('gray'),
            // LoaderWidget
            LoaderWidget::class.'::spinner' => (new Style())->withColor('cyan'),
            LoaderWidget::class.'::message' => (new Style())->withColor('gray'),
            // InputWidget
            InputWidget::class.'::cursor' => new Style(null, null, null, null, null, null, null, null, null, null, null, null, null, CursorShape::Block),
            InputWidget::class.'::placeholder' => new Style(null, null, null, null, null, true),
            InputWidget::class.'::suggestion' => new Style(null, null, null, null, null, true),
            // EditorWidget
            EditorWidget::class.'::cursor' => new Style(null, null, null, null, null, null, null, null, null, null, null, null, null, CursorShape::Block),
            EditorWidget::class.'::frame' => (new Style())->withColor('gray'),
            // SelectListWidget
            SelectListWidget::class.'::group' => new Style(null, null, null, null, true),
            SelectListWidget::class.'::selected' => (new Style())->withBold(),
            SelectListWidget::class.'::selected:focus' => (new Style())->withBold(),
            SelectListWidget::class.'::description' => (new Style())->withColor('gray'),
            SelectListWidget::class.'::scroll-info' => (new Style())->withColor('gray'),
            SelectListWidget::class.'::no-match' => (new Style())->withColor('yellow'),
            // SettingsListWidget
            SettingsListWidget::class.'::label-selected' => (new Style())->withBold(),
            SettingsListWidget::class.'::label-selected:focus' => (new Style())->withBold(),
            SettingsListWidget::class.'::value' => (new Style())->withColor('gray'),
            SettingsListWidget::class.'::value-selected' => (new Style())->withColor('cyan'),
            SettingsListWidget::class.'::value-selected:focus' => (new Style())->withColor('cyan'),
            SettingsListWidget::class.'::description' => (new Style())->withColor('gray'),
            SettingsListWidget::class.'::hint' => (new Style())->withColor('gray'),
            // TabsWidget
            TabsWidget::class.'::tab' => (new Style())->withColor('gray'),
            TabsWidget::class.'::tab-active' => (new Style())->withBold()->withColor('cyan'),
            TabsWidget::class.'::tab:focus' => new Style(null, null, '#253041'),
            TabsWidget::class.'::tab-active:focus' => (new Style())->withBold()->withColor('cyan')->withBackground('#253041'),
            TabsWidget::class.'::separator' => (new Style())->withColor('gray'),
            // MarkdownWidget
            MarkdownWidget::class.'::heading' => (new Style())->withColor('cyan')->withBold(),
            MarkdownWidget::class.'::link' => (new Style())->withColor('blue')->withUnderline(),
            MarkdownWidget::class.'::link-url' => (new Style())->withColor('gray'),
            MarkdownWidget::class.'::code' => (new Style())->withColor('yellow'),
            MarkdownWidget::class.'::code-block-border' => (new Style())->withColor('gray'),
            MarkdownWidget::class.'::quote' => (new Style())->withItalic(),
            MarkdownWidget::class.'::quote-border' => (new Style())->withColor('gray'),
            MarkdownWidget::class.'::hr' => (new Style())->withColor('gray'),
            MarkdownWidget::class.'::list-bullet' => (new Style())->withColor('cyan'),
            MarkdownWidget::class.'::bold' => (new Style())->withBold(),
            MarkdownWidget::class.'::italic' => (new Style())->withItalic(),
            MarkdownWidget::class.'::strikethrough' => (new Style())->withStrikethrough(),
        ]);
    }
}
