<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\TuiHtml\Tests\Compatibility;

use Symfony\Bridge\TuiHtml\Css\CssStyleSheet;
use Symfony\Bridge\TuiHtml\Css\FilesystemStylesheetLoader;
use Symfony\Bridge\TuiHtml\Html\HtmlBuilder;
use Symfony\Bridge\TuiHtml\Render\FacadeRenderer;
use Symfony\Bridge\TuiHtml\Style\CellSize;
use Symfony\Bridge\TuiHtml\Style\ColorScheme;
use Symfony\Bridge\TuiHtml\Style\Density;
use Symfony\Bridge\TuiHtml\Style\MediaContext;
use Symfony\Bridge\TuiHtml\Tests\Fixtures\HtmlTui as Tui;
use Symfony\Bridge\TuiHtml\Tests\Fixtures\WordListHyphenator;
use Symfony\Bridge\TuiHtml\Widget\ContainerWidget;
use Symfony\Bridge\TuiHtml\Widget\DropdownWidget;
use Symfony\Component\Tui\Render\Renderer as NativeRenderer;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;

/** Replays the original native facade scenario with an explicit renderer choice. */
final class FacadeScenario
{
    private const DIR = __DIR__.'/../Fixtures/Compat';

    public static function render(string $html, array $options, bool $core = false): string
    {
        $columns = (int) ($options['columns'] ?? 60);
        $rows = (int) ($options['rows'] ?? 80);
        // hyphens: auto with dictionaries for the words of the fixtures, no hyphenation library being required
        $styleSheet = new CssStyleSheet(new FilesystemStylesheetLoader([self::DIR]), null, false, null, WordListHyphenator::forFixtures());
        $styleSheet->setAnimationHandlers(null, static fn (): float => 0.0);
        $builder = new HtmlBuilder($styleSheet);
        if ($core) {
            $builder->useHtmlRenderer();
        }
        $view = $builder->build($html);
        $terminal = new VirtualTerminal($columns, $rows);
        $tui = new Tui(null, $terminal, null, null, $core ? new FacadeRenderer(new NativeRenderer()) : null);
        $tui->setMediaContext(new MediaContext(80, 24, ColorScheme::from($options['scheme'] ?? 'dark'), Density::from($options['density'] ?? 'regular'), isset($options['contrast']), isset($options['forced-colors']), isset($options['reduced-motion']), isset($options['dppx']) ? new CellSize(8 * (int) $options['dppx'], 16 * (int) $options['dppx']) : null, isset($options['pointer']), (int) ($options['colors'] ?? MediaContext::TRUE_COLORS), isset($options['text-sizing'])));
        try {
            $view->mount($tui);
            if (isset($options['focus'])) {
                $focus = $view->querySelector($options['focus']);
                // A <select> drawn as a dropdown takes the focus on its field, like HtmlView does
                $tui->setFocus($focus instanceof DropdownWidget ? $focus->getField() : $focus);
            }
            if (isset($options['modal'])) {
                $view->showModal($view->querySelector($options['modal']) ?? throw new \LogicException(\sprintf('No "%s" to show.', $options['modal'])));
            }
            if (isset($options['target'])) {
                $view->navigate($options['target']);
            }
            // Links visited, like visited=/docs,https://symfony.com
            if (isset($options['visited'])) {
                $view->setVisitedLinks(explode(',', $options['visited']));
            }
            if (isset($options['hover'])) {
                $view->querySelector($options['hover'])?->setState('hover');
            }
            // Custom highlights, like highlight=found:needle,other:two_words
            foreach (isset($options['highlight']) ? explode(',', $options['highlight']) : [] as $highlight) {
                [$name, $text] = explode(':', $highlight, 2) + [1 => ''];
                $view->highlight($name, str_replace('_', ' ', $text));
            }
            $tui->start();
            $tui->processRender();
            // Scroll containers scrolled after the first frame, like scroll=.list:3 or scroll-x=.row:10
            foreach (['scroll' => 'scrollTo', 'scroll-x' => 'scrollToColumn'] as $option => $method) {
                if (isset($options[$option])) {
                    [$selector, $offset] = explode(':', $options[$option], 2) + [1 => '0'];
                    $scroller = $view->querySelector($selector);
                    if (!$scroller instanceof ContainerWidget) {
                        throw new \LogicException(\sprintf('No "%s" to scroll.', $selector));
                    }
                    $scroller->{$method}((int) $offset);
                    $tui->requestRender();
                    $tui->processRender();
                }
            }
            // Clicks after the first frame, at a row and a column of the screen, like click=3:0
            foreach (array_filter(explode(',', $options['click'] ?? '')) as $cell) {
                [$clickRow, $clickCol] = array_map('intval', explode(':', $cell, 2) + [1 => '0']);
                $view->click($clickRow, $clickCol);
                $tui->requestRender();
                $tui->processRender();
            }
            // Keys pressed after the first frame, like keys=enter,down
            foreach (array_filter(explode(',', $options['keys'] ?? '')) as $key) {
                $tui->handleInput(match ($key) {
                    'enter' => "\r",
                    'down' => "\x1b[B",
                    'up' => "\x1b[A",
                    'alt+down' => "\x1b[1;3B",
                    'escape' => "\x1b",
                    'tab' => "\t",
                    // The focus cycle
                    'f6' => "\x1b[17~",
                    'space' => ' ',
                    // A character types itself
                    default => 1 === mb_strlen($key) ? $key : throw new \LogicException(\sprintf('Unknown key "%s".', $key)),
                });
                $tui->processRender();
            }
            // Before stop(), which moves the cursor below the frame: a frame as tall as the screen would scroll
            $output = $terminal->getOutput();
        } finally {
            $view->unmount();
            $tui->stop();
        }
        $screen = new ScreenBuffer($columns, $rows);
        $screen->write($output);

        // Escapes made visible, so that snapshots can be reviewed
        return str_replace("\x1b[", '⟨', $screen->getStyledScreen())."\n";
    }
}
