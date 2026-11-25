<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Bridge\TuiHtml\Html\HtmlBuilder;
use Symfony\Bridge\TuiHtml\Render\Renderer;
use Symfony\Bridge\TuiHtml\Render\TerminalTextMeasurer;
use Symfony\Bridge\TuiHtml\Widget\DocumentWidget;
use Symfony\Component\HtmlRenderer\HtmlRenderer;
use Symfony\Component\HtmlRenderer\RenderingContext;
use Symfony\Component\Tui\Render\RenderContext;

require dirname(__DIR__).'/bootstrap.php';

$cpu = static function (): float {
    $usage = getrusage();

    return $usage['ru_utime.tv_sec'] + $usage['ru_stime.tv_sec'] + ($usage['ru_utime.tv_usec'] + $usage['ru_stime.tv_usec']) / 1e6;
};
$sourceHash = static function (): array {
    $files = [];
    foreach ([dirname(__DIR__, 2), dirname((new ReflectionClass(HtmlRenderer::class))->getFileName()), dirname((new ReflectionClass(Symfony\Component\Tui\Tui::class))->getFileName())] as $directory) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)) as $file) {
            $relative = substr($file->getPathname(), strlen($directory) + 1);
            if (!preg_match('~(?:^|/)(Tests|vendor)/~', $relative) && ('composer.json' === $file->getFilename() || in_array($file->getExtension(), ['php', 'css'], true))) {
                $files[basename($directory).'/'.$relative] = hash_file('sha256', $file->getPathname());
            }
        }
    }
    ksort($files);

    return ['sha256' => hash('sha256', json_encode($files, \JSON_THROW_ON_ERROR)), 'files' => count($files)];
};
$before = $sourceHash();
$paragraphs = implode('', array_map(static fn ($i) => '<p id="p'.$i.'">Paragraph '.$i.' with Unicode café 中文, a <a href="#p1">link</a> and wrapping text.</p>', range(1, 80)));
$cases = [
    'blocks' => '<style>p{margin:0 0 1em} article{padding:1ch}</style><article>'.$paragraphs.'</article>',
    'flex' => '<style>main{display:flex;flex-wrap:wrap;gap:1ch}article{width:24ch}p{margin:0}</style><main>'.str_repeat('<article><h2>Card</h2><p>Text wraps across columns. Unicode 中文 café.</p></article>', 30).'</main>',
    'grid' => '<style>main{display:grid;grid-template-columns:repeat(3,1fr);gap:1ch}p{margin:0}</style><main>'.str_repeat('<article><h2>Grid</h2><p>Content wraps inside its box.</p></article>', 30).'</main>',
];
$results = [];
foreach ($cases as $name => $html) {
    $samples = $hashes = [];
    for ($sample = 0; $sample < 9; ++$sample) {
        // Alternate execution order to reduce systematic warm-up bias.
        foreach ($sample % 2 ? ['standalone', 'legacy'] : ['legacy', 'standalone'] as $engine) {
            $start = $cpu();
            if ('legacy' === $engine) {
                $view = (new HtmlBuilder())->build($html);
                $renderer = new Renderer();
                $renderer->getStyleSheet()->addProvider($view->getStyleSheet());
                $render = static fn () => $renderer->renderFrame($view->getRoot(), 100, 40);
            } else {
                $session = (new HtmlRenderer())->createSession($html, new RenderingContext(800, 640, new TerminalTextMeasurer()));
                $widget = new DocumentWidget($session);
                $render = static fn () => $widget->render(new RenderContext(100, 40));
            }
            $lines = $render();
            $samples[$engine]['cold'][] = ($cpu() - $start) * 1000;
            $hashes[$engine][] = hash('sha256', json_encode('legacy' === $engine ? $lines->toArray() : $lines, \JSON_THROW_ON_ERROR));
            $start = $cpu();
            for ($frame = 0; $frame < 20; ++$frame) {
                $render();
            }
            $samples[$engine]['cached'][] = ($cpu() - $start) * 1000 / 20;
            unset($render, $view, $renderer, $session, $widget, $lines);
            gc_collect_cycles();
        }
    }
    $result = ['case' => $name, 'html_sha256' => hash('sha256', $html)];
    foreach ($samples as $engine => $timings) {
        foreach ($timings as $phase => $values) {
            sort($values);
            $result[$engine][$phase.'_cpu_ms'] = $values[4];
        }
        $result[$engine]['output_sha256'] = array_values(array_unique($hashes[$engine]));
    }
    $result['output_equal'] = $result['legacy']['output_sha256'] === $result['standalone']['output_sha256'];
    $results[] = $result;
}
$after = $sourceHash();
$receipt = ['php' => \PHP_VERSION, 'viewport_cells' => [100, 40], 'cell_metrics' => [8, 16], 'terminal_policy' => 'DocumentWidget defaults; legacy HtmlBuilder defaults', 'clock' => 'getrusage user + system CPU', 'samples' => 9, 'cached_frames_per_sample' => 20, 'source_before' => $before, 'source_after' => $after, 'source_coherent' => $before === $after, 'results' => $results, 'peak_bytes' => memory_get_peak_usage(true), 'interpretation' => 'Output hashes describe different rendering paths. Timing differences are not equivalent-output speedup claims.'];
echo json_encode($receipt, \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR).\PHP_EOL;
exit($receipt['source_coherent'] ? 0 : 2);
