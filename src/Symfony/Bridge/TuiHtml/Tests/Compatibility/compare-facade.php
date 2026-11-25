<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Bridge\TuiHtml\Tests\Compatibility\FacadeScenario;
use Symfony\Bridge\TuiHtml\Tests\Compatibility\FixtureVariants;
use Symfony\Bridge\TuiHtml\Tests\Compatibility\ProductionFingerprint;
use Symfony\Bridge\TuiHtml\Tests\Compatibility\TerminalCellSnapshot;
use Symfony\Component\Tui\Ansi\AnsiUtils;

require dirname(__DIR__).'/bootstrap.php';

$arguments = getopt('', ['output:', 'case:']);
$directory = __DIR__.'/../Fixtures/Compat';
if (isset($arguments['output']) && str_starts_with((realpath(dirname($arguments['output'])) ?: '').'/', realpath($directory).'/')) {
    fwrite(\STDERR, "The comparison receipt must be outside the compliance fixture directory.\n");
    exit(2);
}
$before = ProductionFingerprint::capture();
$tooling = [];
foreach ([__FILE__, __DIR__.'/FacadeScenario.php', __DIR__.'/FixtureVariants.php', __DIR__.'/TerminalCellSnapshot.php', __DIR__.'/ProductionFingerprint.php'] as $file) {
    $tooling[$file] = hash_file('sha256', $file);
}
$fixtures = [];
$results = [];
$started = hrtime(true);
foreach (glob($directory.'/*') as $file) {
    if (in_array(pathinfo($file, \PATHINFO_EXTENSION), ['html', 'txt'], true)) {
        $fixtures[$file] = hash_file('sha256', $file);
    }
}
foreach (glob($directory.'/*.html') as $file) {
    $html = file_get_contents($file);
    $name = basename($file, '.html');
    $requirements = [];
    preg_match_all('/<!--\s*requires:\s*(.*?)\s*-->/', $html, $matches);
    foreach ($matches[1] as $classes) {
        foreach (preg_split('/\s+/', trim($classes), -1, \PREG_SPLIT_NO_EMPTY) ?: [] as $class) {
            if (!class_exists($class)) {
                $requirements[] = $class;
            }
        }
    }
    foreach (FixtureVariants::fromHtml($html) as [$variant, $options]) {
        $id = $name.'.'.$variant;
        if (isset($arguments['case']) && $arguments['case'] !== $id) {
            continue;
        }
        $columns = (int) ($options['columns'] ?? 60);
        $rows = (int) ($options['rows'] ?? 80);
        $record = ['case_id' => $id, 'fixture' => $name, 'variant' => $variant, 'options' => $options, 'columns' => $columns, 'rows' => $rows, 'missing_requirements' => $requirements, 'transport_only' => isset($options['text-sizing'])];
        if ($requirements) {
            $results[] = $record;
            continue;
        }
        $snapshot = $directory.'/'.$name.'.'.$variant.'.txt';
        if (defined('GD_BUNDLED') && \GD_BUNDLED && is_file($bundled = $directory.'/'.$name.'.'.$variant.'.bundled-gd.txt')) {
            $snapshot = $bundled;
        }
        try {
            $expected = file_get_contents($snapshot);
            $actual = FacadeScenario::render($html, $options, true);
            $plainExpected = AnsiUtils::stripAnsiCodes(str_replace('⟨', "\x1b[", $expected));
            $plainActual = AnsiUtils::stripAnsiCodes(str_replace('⟨', "\x1b[", $actual));
            $first = null;
            $expectedRows = explode("\n", $plainExpected);
            $actualRows = explode("\n", $plainActual);
            for ($row = 0, $count = max(count($expectedRows), count($actualRows)); $row < $count; ++$row) {
                if (($expectedRows[$row] ?? null) !== ($actualRows[$row] ?? null)) {
                    $first = ['row' => $row, 'expected' => $expectedRows[$row] ?? null, 'actual' => $actualRows[$row] ?? null];
                    break;
                }
            }
            $expectedCells = TerminalCellSnapshot::normalize($expected, $columns, $rows, [229, 229, 229], [0, 0, 0]);
            $actualCells = TerminalCellSnapshot::normalize($actual, $columns, $rows, [229, 229, 229], [0, 0, 0]);
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
            $results[] = $record + ['styled_equal' => $expected === $actual, 'plain_equal' => $plainExpected === $plainActual, 'cell_equal' => $expectedCells === $actualCells, 'first_difference' => $first, 'first_cell_difference' => $firstCell, 'expected' => $expected, 'actual' => $actual];
        } catch (Throwable $error) {
            $unsupported = (bool) preg_match('/prototype does not yet|require presentation projection|Nested facade documents|portable content representation|not yet projected/', $error->getMessage());
            $results[] = $record + ['error' => $error::class.': '.$error->getMessage(), 'unsupported' => $unsupported];
        }
    }
}
$after = ProductionFingerprint::capture();
$fixturesUnchanged = true;
foreach ($fixtures as $file => $hash) {
    $fixturesUnchanged = $fixturesUnchanged && hash_file('sha256', $file) === $hash;
}
$toolingUnchanged = true;
foreach ($tooling as $file => $hash) {
    $toolingUnchanged = $toolingUnchanged && hash_file('sha256', $file) === $hash;
}
$summary = ['total' => count($results), 'plain_equal' => count(array_filter($results, static fn ($r) => $r['plain_equal'] ?? false)), 'cell_equal' => count(array_filter($results, static fn ($r) => $r['cell_equal'] ?? false)), 'styled_equal' => count(array_filter($results, static fn ($r) => $r['styled_equal'] ?? false)), 'unsupported' => count(array_filter($results, static fn ($r) => $r['unsupported'] ?? false)), 'errors' => count(array_filter($results, static fn ($r) => isset($r['error']) && !($r['unsupported'] ?? false))), 'unavailable' => count(array_filter($results, static fn ($r) => (bool) $r['missing_requirements'])), 'source_coherent' => $before === $after && $toolingUnchanged, 'fixtures_unchanged' => $fixturesUnchanged, 'seconds' => (hrtime(true) - $started) / 1e9, 'peak_bytes' => memory_get_peak_usage(true)];
$receipt = ['metadata' => ['runtime' => ['php' => \PHP_VERSION, 'intl' => extension_loaded('intl'), 'gd' => extension_loaded('gd')], 'case_filter' => $arguments['case'] ?? null, 'scenario' => 'Exact shared original ConformanceTest native facade scenario; explicit core opt-in, all original variants retained.', 'terminal_profile' => ['foreground' => [229, 229, 229], 'background' => [0, 0, 0]], 'transport' => 'Text-sizing comparisons cover reserved cells/protocol bytes, not device glyph rasterization.', 'source_before' => $before, 'source_after' => $after], 'summary' => $summary, 'cases' => $results];
if (isset($arguments['output'])) {
    file_put_contents($arguments['output'], json_encode($receipt, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_INVALID_UTF8_SUBSTITUTE | \JSON_THROW_ON_ERROR)."\n");
}
echo json_encode($summary, \JSON_THROW_ON_ERROR)."\n";
exit(!$summary['total'] || !$summary['source_coherent'] || !$summary['fixtures_unchanged'] ? 2 : ($summary['cell_equal'] === $summary['total'] ? 0 : 1));
