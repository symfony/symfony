<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require dirname(__DIR__).'/bootstrap.php';

use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;

$options = getopt('', ['samples:']);
$samples = filter_var($options['samples'] ?? 9, \FILTER_VALIDATE_INT, ['options' => ['min_range' => 3, 'max_range' => 99]]);
if (false === $samples) {
    fwrite(\STDERR, "Usage: php benchmark.php [--samples=9], between 3 and 99 samples.\n");
    exit(2);
}
$cpuClock = function_exists('getrusage');
$clock = static function () use ($cpuClock): float {
    if (!$cpuClock) {
        return hrtime(true) / 1000000;
    }
    $usage = getrusage();

    return ($usage['ru_utime.tv_sec'] + $usage['ru_stime.tv_sec']) * 1000 + ($usage['ru_utime.tv_usec'] + $usage['ru_stime.tv_usec']) / 1000;
};
$median = static function (array $values): float {
    sort($values);
    $count = count($values);

    return $count % 2 ? $values[intdiv($count, 2)] : ($values[$count / 2 - 1] + $values[$count / 2]) / 2;
};
$sourceHash = static function (): string {
    $files = [];
    $root = dirname(__DIR__, 2);
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
        $relative = substr($file->getPathname(), strlen($root) + 1);
        if (!preg_match('~^(?:Tests|vendor)/~', $relative) && ('php' === $file->getExtension() || 'composer.json' === $relative)) {
            $files[$relative] = hash_file('sha256', $file->getPathname());
        }
    }
    ksort($files);

    return hash('sha256', serialize($files));
};
$before = $sourceHash();
$cards = str_repeat('<article><h2>Card</h2><p>Text content for layout and wrapping.</p></article>', 30);
$workloads = [
    'block' => '<style>p{margin:4px 0}</style>'.str_repeat('<p>Text content for layout and wrapping.</p>', 80),
    'flex' => '<style>main{display:flex;flex-wrap:wrap;gap:8px}article{width:140px}h2,p{margin:4px 0}</style><main>'.$cards.'</main>',
    'grid' => '<style>main{display:grid;grid-template-columns:repeat(4,1fr);gap:8px}h2,p{margin:4px 0}</style><main>'.$cards.'</main>',
    'table' => '<table>'.str_repeat('<tr>'.str_repeat('<td>Cell text</td>', 12).'</tr>', 50).'</table>',
];
$results = [];
foreach ($workloads as $name => $html) {
    gc_collect_cycles();
    memory_reset_peak_usage();
    $cold = $cached = [];
    $hashes = [];
    for ($sample = 0; $sample < $samples; ++$sample) {
        $renderer = new HtmlRenderer();
        $context = new RenderingContext(800, 640);
        $start = $clock();
        $session = $renderer->createSession('<!DOCTYPE html>'.$html, $context);
        $result = $session->render();
        $cold[] = $clock() - $start;
        $hashes[] = hash('sha256', serialize($result->getDisplayList()->getOperations()));
        $start = $clock();
        for ($frame = 0; $frame < 20; ++$frame) {
            if ($result !== $session->render()) {
                throw new RuntimeException('An unchanged document did not reuse its frame.');
            }
        }
        $cached[] = ($clock() - $start) / 20;
        unset($session, $result, $renderer, $context);
    }
    if (1 !== count(array_unique($hashes))) {
        throw new RuntimeException('Cold display lists were not deterministic.');
    }
    $results[$name] = ['cold_median_ms' => $median($cold), 'cached_median_ms' => $median($cached), 'peak_bytes' => memory_get_peak_usage(true), 'display_list_sha256' => $hashes[0]];
}
$after = $sourceHash();
echo json_encode(['php' => \PHP_VERSION, 'clock' => $cpuClock ? 'process-cpu' : 'wall', 'samples' => $samples, 'cached_frames_per_sample' => 20, 'viewport' => [800, 640], 'measurement' => 'deterministic-monospace', 'source_coherent' => $before === $after, 'source_sha256_before' => $before, 'source_sha256_after' => $after, 'benchmark_sha256' => hash_file('sha256', __FILE__), 'workloads' => $results], \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR), "\n";
exit($before === $after ? 0 : 2);
