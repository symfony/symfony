<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Tests\Compatibility;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Terminal\ScreenBuffer;
use Symfony\Component\Tui\Terminal\VirtualTerminal;
use Symfony\Component\TuiHtml\Compatibility\CompatibilityReport;
use Symfony\Component\TuiHtml\Compatibility\SupportLevel;
use Symfony\Component\TuiHtml\Css\CssStyleSheet;
use Symfony\Component\TuiHtml\Css\FilesystemStylesheetLoader;
use Symfony\Component\TuiHtml\Html\HtmlBuilder;
use Symfony\Component\TuiHtml\Style\CellSize;
use Symfony\Component\TuiHtml\Style\ColorScheme;
use Symfony\Component\TuiHtml\Style\Density;
use Symfony\Component\TuiHtml\Style\MediaContext;
use Symfony\Component\TuiHtml\Tests\Fixtures\HtmlTui as Tui;
use Symfony\Component\TuiHtml\Widget\ContainerWidget;
use Symfony\Component\TuiHtml\Widget\DropdownWidget;

/**
 * Renders the fixtures of Fixtures/Compat and compares them to their snapshots.
 *
 * Each fixture starts with comments:
 *
 *     <!-- covers: css.properties.color css.types.color.rgb -->
 *     <!-- variant: dark-40 columns=40 scheme=dark -->
 *     <!-- requires: GdImage IntlChar -->
 *
 * and is rendered in the regular and condensed densities, plus each variant, unless a class it requires is missing
 * (options: columns, rows, scheme, density, contrast, forced-colors,
 * reduced-motion, dppx, pointer, colors, text-sizing, focus=#id, hover=#id, target=#id, visited=href,..., highlight=name:text_with_spaces,..., scroll=#id:rows, scroll-x=#id:columns,
 * click=row:col, keys=enter,down,f6...). Run with TUI_UPDATE_COMPAT=1
 * to write the snapshots, then review them.
 */
final class ConformanceTest extends TestCase
{
    private const DIR = __DIR__.'/../Fixtures/Compat';

    #[DataProvider('provideFixtures')]
    public function testFixture(string $fixture, string $variant, array $options)
    {
        $html = file_get_contents(self::DIR.'/'.$fixture.'.html');
        // The expected output needs every class the fixture names, like GD for images and IntlChar for visual RTL.
        preg_match_all('/<!--\s*requires:\s*(.*?)\s*-->/', $html, $requirements);
        foreach ($requirements[1] as $classes) {
            foreach (preg_split('/\s+/', trim($classes), -1, \PREG_SPLIT_NO_EMPTY) ?: [] as $class) {
                if (!class_exists($class)) {
                    $this->markTestSkipped(\sprintf('The %s class is needed.', $class));
                }
            }
        }
        $snapshot = self::DIR.'/'.$fixture.'.'.$variant.'.txt';
        // Bundled and external GD rasterize some shape edges differently: each reference stays exact.
        if (\defined('GD_BUNDLED') && \GD_BUNDLED && file_exists($bundled = self::DIR.'/'.$fixture.'.'.$variant.'.bundled-gd.txt')) {
            $snapshot = $bundled;
        }
        $actual = $this->render($html, $options);
        if (getenv('TUI_UPDATE_COMPAT')) {
            file_put_contents($snapshot, $actual);
            $this->addToAssertionCount(1);

            return;
        }
        $this->assertFileExists($snapshot, 'Run the tests with TUI_UPDATE_COMPAT=1 to write the snapshot.');
        if (str_contains($html, '<!-- gd-color-rounding -->')) {
            AnsiSnapshot::assertSameWithColorRounding(file_get_contents($snapshot), $actual);
        } else {
            $this->assertSame(file_get_contents($snapshot), $actual);
        }
    }

    public static function provideFixtures(): iterable
    {
        foreach (glob(self::DIR.'/*.html') as $file) {
            $fixture = basename($file, '.html');
            $html = file_get_contents($file);
            yield $fixture.' regular' => [$fixture, 'regular', []];
            yield $fixture.' condensed' => [$fixture, 'condensed', ['density' => 'condensed']];
            preg_match_all('/<!--\s*variant:\s*(\S+)(.*?)-->/', $html, $variants, \PREG_SET_ORDER);
            foreach ($variants as [, $name, $options]) {
                preg_match_all('/([a-z-]+)(?:=(\S+))?/', $options, $pairs, \PREG_SET_ORDER);
                yield $fixture.' '.$name => [$fixture, $name, array_column(array_map(static fn (array $p) => [$p[1], $p[2] ?? '1'], $pairs), 1, 0)];
            }
        }
    }

    public function testEverySupportedFeatureIsCovered()
    {
        $covered = [];
        foreach (glob(self::DIR.'/*.html') as $file) {
            if (preg_match('/<!--\s*covers:(.*?)-->/s', file_get_contents($file), $m)) {
                $covered += array_fill_keys(preg_split('/\s+/', trim($m[1])), true);
            }
        }
        $missing = [];
        foreach (CompatibilityReport::createDefault()->getFeatures() as $key => $support) {
            if (SupportLevel::Ignored !== $support->level && !isset($covered[$key])) {
                $missing[] = $key;
            }
        }
        $this->assertSame([], $missing, 'Every supported or partially supported feature needs a conformance fixture.');
        $this->assertSame([], array_diff(array_keys($covered), array_keys(CompatibilityReport::createDefault()->getFeatures())), 'Fixtures only cover known features.');
    }

    private function render(string $html, array $options): string
    {
        $columns = (int) ($options['columns'] ?? 60);
        $rows = (int) ($options['rows'] ?? 80);
        $styleSheet = new CssStyleSheet(new FilesystemStylesheetLoader([self::DIR]), null, false);
        $styleSheet->setAnimationHandlers(null, static fn (): float => 0.0);
        $builder = new HtmlBuilder($styleSheet);
        $view = $builder->build($html);
        $terminal = new VirtualTerminal($columns, $rows);
        $tui = new Tui(null, $terminal);
        $tui->setMediaContext(new MediaContext(80, 24, ColorScheme::from($options['scheme'] ?? 'dark'), Density::from($options['density'] ?? 'regular'), isset($options['contrast']), isset($options['forced-colors']), isset($options['reduced-motion']), isset($options['dppx']) ? new CellSize(8 * (int) $options['dppx'], 16 * (int) $options['dppx']) : null, isset($options['pointer']), (int) ($options['colors'] ?? MediaContext::TRUE_COLORS), isset($options['text-sizing'])));
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
        $tui->stop();
        $screen = new ScreenBuffer($columns, $rows);
        $screen->write($output);

        // Escapes made visible, so that snapshots can be reviewed
        return str_replace("\x1b[", '⟨', $screen->getStyledScreen())."\n";
    }
}
