<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Style;

use Symfony\Component\Tui\Widget\CancellableLoaderWidget;
use Symfony\Component\Tui\Widget\EditorWidget;
use Symfony\Component\Tui\Widget\InputWidget;
use Symfony\Component\Tui\Widget\LoaderWidget;
use Symfony\Component\Tui\Widget\MarkdownWidget;
use Symfony\Component\Tui\Widget\SelectListWidget;
use Symfony\Component\Tui\Widget\SettingsListWidget;

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
            '.h1' => new Style(bold: true, color: 'cyan'),
            '.h2' => new Style(bold: true, color: 'blue'),
            '.h3' => new Style(bold: true),
            '.h4' => new Style(bold: true, dim: true),
            '.h5' => new Style(dim: true),
            '.h6' => new Style(dim: true, italic: true),
            '.hr' => new Style(color: 'gray'),
            '.p' => new Style(),

            // Layout aliases (used by <columns>/<column> tag aliases)
            '.columns' => new Style(direction: Direction::Horizontal, gap: 2),
            '.column' => new Style(),

            // CancellableLoaderWidget
            CancellableLoaderWidget::class.':focus' => new Style()->withBold(),

            // LoaderWidget
            LoaderWidget::class.'::spinner' => new Style()->withColor('cyan'),
            LoaderWidget::class.'::message' => new Style()->withColor('gray'),

            // InputWidget
            InputWidget::class.'::cursor' => new Style(cursorShape: CursorShape::Block),

            // EditorWidget
            EditorWidget::class.'::cursor' => new Style(cursorShape: CursorShape::Block),
            EditorWidget::class.'::frame' => new Style()->withColor('gray'),

            // SelectListWidget
            SelectListWidget::class.'::selected' => new Style()->withBold(),
            SelectListWidget::class.'::selected:focus' => new Style()->withBold(),
            SelectListWidget::class.'::description' => new Style()->withColor('gray'),
            SelectListWidget::class.'::scroll-info' => new Style()->withColor('gray'),
            SelectListWidget::class.'::no-match' => new Style()->withColor('yellow'),

            // SettingsListWidget
            SettingsListWidget::class.'::label-selected' => new Style()->withBold(),
            SettingsListWidget::class.'::label-selected:focus' => new Style()->withBold(),
            SettingsListWidget::class.'::value' => new Style()->withColor('gray'),
            SettingsListWidget::class.'::value-selected' => new Style()->withColor('cyan'),
            SettingsListWidget::class.'::value-selected:focus' => new Style()->withColor('cyan'),
            SettingsListWidget::class.'::description' => new Style()->withColor('gray'),
            SettingsListWidget::class.'::hint' => new Style()->withColor('gray'),

            // MarkdownWidget
            MarkdownWidget::class.'::heading' => new Style()->withColor('cyan')->withBold(),
            MarkdownWidget::class.'::link' => new Style()->withColor('blue')->withUnderline(),
            MarkdownWidget::class.'::link-url' => new Style()->withColor('gray'),
            MarkdownWidget::class.'::code' => new Style()->withColor('yellow'),
            MarkdownWidget::class.'::code-block-border' => new Style()->withColor('gray'),
            MarkdownWidget::class.'::quote' => new Style()->withItalic(),
            MarkdownWidget::class.'::quote-border' => new Style()->withColor('gray'),
            MarkdownWidget::class.'::hr' => new Style()->withColor('gray'),
            MarkdownWidget::class.'::list-bullet' => new Style()->withColor('cyan'),
            MarkdownWidget::class.'::bold' => new Style()->withBold(),
            MarkdownWidget::class.'::italic' => new Style()->withItalic(),
            MarkdownWidget::class.'::strikethrough' => new Style()->withStrikethrough(),
        ]);
    }
}
