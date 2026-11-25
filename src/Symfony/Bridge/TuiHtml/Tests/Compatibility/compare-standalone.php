<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Bridge\TuiHtml\Css\FilesystemStylesheetLoader;
use Symfony\Bridge\TuiHtml\Render\TerminalColorPalette;
use Symfony\Bridge\TuiHtml\Render\TerminalTextMeasurer;
use Symfony\Bridge\TuiHtml\Tests\Compatibility\DocumentScenario;
use Symfony\Bridge\TuiHtml\Tests\Compatibility\FixtureVariants;
use Symfony\Bridge\TuiHtml\Tests\Compatibility\TerminalCellSnapshot;
use Symfony\Bridge\TuiHtml\Tests\Fixtures\WordListHyphenator;
use Symfony\Bridge\TuiHtml\Widget\DocumentWidget;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;
use Symfony\Component\HtmlRenderer\Resource\ResourceLoader;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Terminal\ScreenBuffer;

require dirname(__DIR__).'/bootstrap.php';

$arguments = getopt('', ['output:', 'foreground:', 'background:', 'case:', 'all-variants', 'legacy-palette']);
$colorLookup = isset($arguments['legacy-palette']) ? TerminalColorPalette::lookupStandardName(...) : null;
$profile = [];
foreach (['foreground' => [229, 229, 229], 'background' => [0, 0, 0]] as $name => $default) {
    if (isset($arguments[$name]) && !preg_match('/^\d{1,3},\d{1,3},\d{1,3}$/', $arguments[$name])) {
        fwrite(\STDERR, 'A color profile requires three comma-separated RGB channels.'.\PHP_EOL);
        exit(2);
    }
    $value = isset($arguments[$name]) ? array_map('intval', explode(',', $arguments[$name])) : $default;
    if (3 !== count($value) || min($value) < 0 || max($value) > 255) {
        fwrite(\STDERR, 'A color profile requires three RGB channels between 0 and 255.'.\PHP_EOL);
        exit(2);
    }
    $profile[$name] = $value;
}
$sourceHash = static function (): array {
    $files = [];
    $counts = ['php' => 0, 'css' => 0, 'composer' => 0];
    foreach (['bridge' => dirname(__DIR__, 2), 'engine' => dirname((new ReflectionClass(HtmlRenderer::class))->getFileName()), 'tui' => dirname((new ReflectionClass(Symfony\Component\Tui\Tui::class))->getFileName())] as $label => $directory) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) {
            $relative = substr($file->getPathname(), strlen($directory) + 1);
            $kind = 'composer.json' === $file->getFilename() ? 'composer' : $file->getExtension();
            if (isset($counts[$kind]) && !preg_match('~(?:^|/)(Tests|vendor)/~', $relative)) {
                $files[$label.'/'.$relative] = hash_file('sha256', $file->getPathname());
                ++$counts[$kind];
            }
        }
    }
    ksort($files);

    return ['sha256' => hash('sha256', json_encode($files, \JSON_THROW_ON_ERROR)), 'production_files' => count($files), 'by_kind' => $counts];
};
$before = $sourceHash();
$tooling = ['script' => hash_file('sha256', __FILE__), 'normalizer' => hash_file('sha256', __DIR__.'/TerminalCellSnapshot.php'), 'variants' => hash_file('sha256', __DIR__.'/FixtureVariants.php'), 'scenarios' => hash_file('sha256', __DIR__.'/DocumentScenario.php')];
$fixtureHashes = [];
$foreground = vsprintf('#%02x%02x%02x', $profile['foreground']);
$dir = __DIR__.'/../Fixtures/Compat';
if (isset($arguments['output']) && str_starts_with((realpath(dirname($arguments['output'])) ?: '').'/', realpath($dir).'/')) {
    fwrite(\STDERR, 'The comparison output must be outside the compliance fixture directory.'.\PHP_EOL);
    exit(2);
}
$loader = new ResourceLoader((new FilesystemStylesheetLoader([$dir]))->load(...));
$results = [];
$started = hrtime(true);
foreach (glob($dir.'/*.html') as $file) {
    $html = file_get_contents($file);
    $fixtureHashes[$file] = hash('sha256', $html);
    $name = basename($file, '.html');
    $missingRequirements = [];
    preg_match_all('/<!--\s*requires:\s*(.*?)\s*-->/', $html, $requirements);
    foreach ($requirements[1] as $classes) {
        foreach (preg_split('/\s+/', trim($classes), -1, \PREG_SPLIT_NO_EMPTY) ?: [] as $class) {
            if (!class_exists($class)) {
                $missingRequirements[] = $class;
            }
        }
    }
    foreach (FixtureVariants::fromHtml($html) as [$variant, $options]) {
        if (!isset($arguments['all-variants']) && 'regular' !== $variant && (!str_contains($variant, 'narrow') || array_diff(array_keys($options), ['columns', 'rows']))) {
            continue;
        }
        if (isset($arguments['case']) && $arguments['case'] !== $name.'.'.$variant) {
            continue;
        }
        $columns = (int) ($options['columns'] ?? 60);
        $rows = (int) ($options['rows'] ?? 80);
        $snapshot = $dir.'/'.$name.'.'.$variant.'.txt';
        if (defined('GD_BUNDLED') && \GD_BUNDLED && file_exists($bundled = $dir.'/'.$name.'.'.$variant.'.bundled-gd.txt')) {
            $snapshot = $bundled;
        }
        if (!file_exists($snapshot)) {
            continue;
        }
        $expected = file_get_contents($snapshot);
        $fixtureHashes[$snapshot] = hash('sha256', $expected);
        try {
            $source = preg_match('/^\s*(<!doctype|<html)/i', $html) ? $html : '<!DOCTYPE html><html><body>'.$html.'</body></html>';
            $document = Dom\HTMLDocument::createFromString($source, \LIBXML_NOERROR);
            $style = $document->createElement('style');
            $style->textContent = 'html,body { margin:0; padding:0; font-size:16px; line-height:16px; color:default; background-color:transparent }';
            $document->head->prepend($style);
            $unsupported = [];
            if (isset($arguments['all-variants'])) {
                [$session, $lines, $unsupported] = DocumentScenario::render($document, $loader, $columns, $rows, $options, $foreground, $colorLookup);
            } else {
                $session = (new HtmlRenderer())->createSession($document, new RenderingContext($columns * 8, $rows * 16, new TerminalTextMeasurer(), $loader, null, null, [], '', ['default' => $foreground], WordListHyphenator::forFixtures(), null, $colorLookup));
                $lines = (new DocumentWidget($session))->render(new RenderContext($columns, $rows));
            }
            $result = $session->render();
            $screen = new ScreenBuffer($columns, $rows);
            $screen->write(implode("\r\n", $lines));
            $actual = str_replace("\x1b[", '⟨', $screen->getStyledScreen())."\n";
            $plainExpected = AnsiUtils::stripAnsiCodes(str_replace('⟨', "\x1b[", $expected));
            $plainActual = AnsiUtils::stripAnsiCodes(str_replace('⟨', "\x1b[", $actual));
            $first = null;
            $e = explode("\n", $plainExpected);
            $a = explode("\n", $plainActual);
            for ($i = 0,$n = max(count($e), count($a)); $i < $n; ++$i) {
                if (($e[$i] ?? null) !== ($a[$i] ?? null)) {
                    $first = ['row' => $i, 'expected' => $e[$i] ?? null, 'actual' => $a[$i] ?? null];
                    break;
                }
            }
            $expectedCells = TerminalCellSnapshot::normalize($expected, $columns, $rows, $profile['foreground'], $profile['background']);
            $actualCells = TerminalCellSnapshot::normalize($actual, $columns, $rows, $profile['foreground'], $profile['background']);
            $firstCell = null;
            $cellRows = array_unique([...array_keys($expectedCells), ...array_keys($actualCells)]);
            sort($cellRows);
            foreach ($cellRows as $row) {
                $cellColumns = array_unique([...array_keys($expectedCells[$row] ?? []), ...array_keys($actualCells[$row] ?? [])]);
                sort($cellColumns);
                foreach ($cellColumns as $column) {
                    if (($expectedCells[$row][$column] ?? null) !== ($actualCells[$row][$column] ?? null)) {
                        $firstCell = ['row' => $row, 'column' => $column, 'expected' => $expectedCells[$row][$column] ?? null, 'actual' => $actualCells[$row][$column] ?? null];
                        break 2;
                    }
                }
            }
            $results[] = ['case_id' => $name.'.'.$variant, 'fixture' => $name, 'variant' => $variant, 'columns' => $columns, 'rows' => $rows, 'styled_equal' => $expected === $actual, 'cell_equal' => $expectedCells === $actualCells, 'first_cell_difference' => $firstCell, 'plain_equal' => $plainExpected === $plainActual, 'first_difference' => $first, 'operations' => count($result->getDisplayList()), 'options' => $options, 'unsupported_options' => $unsupported, 'transport_only' => isset($options['text-sizing']), 'missing_requirements' => $missingRequirements, 'expected' => $expected, 'actual' => $actual];
        } catch (Throwable $e) {
            $results[] = ['case_id' => $name.'.'.$variant, 'fixture' => $name, 'variant' => $variant, 'columns' => $columns, 'rows' => $rows, 'options' => $options, 'missing_requirements' => $missingRequirements, 'error' => $e::class.': '.$e->getMessage()];
        }
    }
}
$after = $sourceHash();
$fixturesUnchanged = true;
foreach ($fixtureHashes as $path => $hash) {
    $fixturesUnchanged = $fixturesUnchanged && hash_file('sha256', $path) === $hash;
}
$summary = [
    'total' => count($results),
    'styled_equal' => count(array_filter($results, static fn ($result) => $result['styled_equal'] ?? false)),
    'plain_equal' => count(array_filter($results, static fn ($result) => $result['plain_equal'] ?? false)),
    'cell_equal' => count(array_filter($results, static fn ($result) => $result['cell_equal'] ?? false)),
    'transport_only' => count(array_filter($results, static fn ($result) => $result['transport_only'] ?? false)),
    'unavailable' => count(array_filter($results, static fn ($result) => [] !== ($result['missing_requirements'] ?? []))),
    'unsupported' => count(array_filter($results, static fn ($result) => [] !== ($result['unsupported_options'] ?? []))),
    'errors' => count(array_filter($results, static fn ($result) => isset($result['error']))),
    'seconds' => (hrtime(true) - $started) / 1e9,
    'peak_bytes' => memory_get_peak_usage(true),
    'source_coherent' => $before === $after && $tooling === ['script' => hash_file('sha256', __FILE__), 'normalizer' => hash_file('sha256', __DIR__.'/TerminalCellSnapshot.php'), 'variants' => hash_file('sha256', __DIR__.'/FixtureVariants.php'), 'scenarios' => hash_file('sha256', __DIR__.'/DocumentScenario.php')],
    'fixtures_unchanged' => $fixturesUnchanged,
];
$receipt = [
    'metadata' => [
        'runtime' => ['php' => \PHP_VERSION, 'intl' => extension_loaded('intl'), 'gd' => extension_loaded('gd')],
        'case_filter' => $arguments['case'] ?? null,
        'cases' => isset($arguments['all-variants']) ? 'All regular, condensed and named fixture variants; normalized focus, modal, navigation, visited, pointer, scroll and keyboard actions. Unsupported actions remain explicit.' : 'Regular variants and narrow variants with viewport options only; no interactive variant state.',
        'hover_policy' => 'Named hover targets use pointer hit testing at the center of their first layout bounds; this is not forced private selector state.',
        'text_sizing' => 'Scaled-text scenarios compare reserved cells and protocol-aware geometry. OSC 66 glyph rasterization is not simulated; native integration tests verify transport bytes separately.',
        'hyphenation' => 'The same fixture-specific dictionary as ConformanceTest is supplied; no external dictionary package is needed.',
        'terminal_profile' => $profile,
        'palette' => 'Existing bridge xterm palette; default foreground/background are explicit assumptions, not detected colors.',
        'computed_color_policy' => null !== $colorLookup ? 'Explicit legacy terminal name lookup for computed color functions and gradients.' : 'CSS-standard color function and gradient operands; legacy lookup is opt-in.',
        'metrics' => ['cell_width' => 8, 'cell_height' => 16],
        'document_defaults' => 'Zero html/body margin/padding, 16px font/line height and terminal default foreground.',
        'comparison_tooling' => $tooling,
        'source_before' => $before,
        'source_after' => $after,
    ],
    'summary' => $summary,
    'cases' => $results,
];
if (isset($arguments['output'])) {
    file_put_contents($arguments['output'], json_encode($receipt, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_INVALID_UTF8_SUBSTITUTE | \JSON_THROW_ON_ERROR).\PHP_EOL);
}
echo json_encode($summary, \JSON_THROW_ON_ERROR).\PHP_EOL;
exit((0 === $summary['total'] || !$summary['source_coherent'] || !$summary['fixtures_unchanged']) ? 2 : ((0 !== $summary['unavailable'] || 0 !== $summary['unsupported'] || 0 !== $summary['errors'] || $summary['cell_equal'] !== $summary['total']) ? 1 : 0));
