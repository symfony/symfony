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

use Symfony\Component\Tui\Ansi\ScreenBufferHtmlRenderer;
use Symfony\Component\Tui\Terminal\ScreenBuffer;

/**
 * Compares TUI output states and generates visual diffs.
 */
final class StateDiff
{
    /**
     * Compare two outputs and return diff information.
     *
     * @return array{
     *     identical: bool,
     *     summary: string,
     *     diff_lines: list<array{type: string, line_num: int|null, content: string, new_content?: string}>,
     *     diff_text: string,
     *     stats: array{added: int, removed: int, changed: int, unchanged: int}
     * }
     */
    public static function compare(string $expected, string $actual): array
    {
        if ($expected === $actual) {
            return [
                'identical' => true,
                'summary' => 'Outputs are identical',
                'diff_lines' => [],
                'diff_text' => '',
                'stats' => ['added' => 0, 'removed' => 0, 'changed' => 0, 'unchanged' => 0],
            ];
        }

        $expectedLines = explode("\n", $expected);
        $actualLines = explode("\n", $actual);

        $diff = self::computeDiff($expectedLines, $actualLines);
        $stats = self::computeStats($diff);

        return [
            'identical' => false,
            'summary' => \sprintf(
                '%d added, %d removed, %d changed, %d unchanged',
                $stats['added'],
                $stats['removed'],
                $stats['changed'],
                $stats['unchanged']
            ),
            'diff_lines' => $diff,
            'diff_text' => self::formatUnifiedDiff($diff, $expectedLines, $actualLines),
            'stats' => $stats,
        ];
    }

    /**
     * Generate a side-by-side comparison.
     *
     * @return string Terminal-formatted side-by-side view
     */
    public static function sideBySide(string $expected, string $actual, int $width = 80): string
    {
        $expectedLines = explode("\n", $expected);
        $actualLines = explode("\n", $actual);
        $maxLines = max(\count($expectedLines), \count($actualLines));
        $colWidth = (int) (($width - 3) / 2);

        $output = [];
        $output[] = str_repeat('─', $colWidth).' │ '.str_repeat('─', $colWidth);
        $output[] = str_pad('Expected', $colWidth).' │ '.str_pad('Actual', $colWidth);
        $output[] = str_repeat('─', $colWidth).' │ '.str_repeat('─', $colWidth);

        for ($i = 0; $i < $maxLines; ++$i) {
            $left = $expectedLines[$i] ?? '';
            $right = $actualLines[$i] ?? '';

            $left = mb_substr($left, 0, $colWidth);
            $right = mb_substr($right, 0, $colWidth);

            $left = str_pad($left, $colWidth);
            $right = str_pad($right, $colWidth);

            $marker = ($expectedLines[$i] ?? '') === ($actualLines[$i] ?? '') ? '│' : '≠';
            $output[] = $left.' '.$marker.' '.$right;
        }

        $output[] = str_repeat('─', $colWidth).' │ '.str_repeat('─', $colWidth);

        return implode("\n", $output);
    }

    /**
     * Generate HTML diff report.
     *
     * @param array<string, array{expected: string, expected_raw?: string, actual: string, actual_raw?: string, step: string, example?: string, all_steps?: list<array{action: string, input: string, output_raw?: string}>}> $failures
     * @param array<string, array{example: string, all_steps: list<array{action: string, input: string, output_raw?: string}>, step_count: int}>                                                                              $successes
     */
    public static function generateHtmlReport(array $failures, array $successes = [], int $totalSteps = 0, int $passedSteps = 0, string $title = 'TUI Regression Report'): string
    {
        $failureCount = \count($failures);
        $successCount = \count($successes);
        $hasFailures = $failureCount > 0;

        $titleColor = $hasFailures ? '#d32f2f' : '#2e7d32';
        $titleIcon = $hasFailures ? '🔴' : '✅';
        $summaryBg = $hasFailures ? '#ffebee' : '#e8f5e9';

        $html = <<<'HTML'
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="UTF-8">
                <title>%TITLE%</title>
                <style>
                    body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; margin: 20px; background: #f5f5f5; }
                    h1 { color: %TITLE_COLOR%; }
                    h2.section-title { color: #333; margin-top: 30px; padding-bottom: 10px; border-bottom: 2px solid #ddd; }
                    .example { background: white; border: 1px solid #ddd; border-radius: 8px; margin: 20px 0; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
                    .example.failed { border-left: 4px solid #d32f2f; }
                    .example.passed { border-left: 4px solid #2e7d32; }
                    .example h3 { margin-top: 0; color: #333; border-bottom: 1px solid #eee; padding-bottom: 10px; display: flex; align-items: center; gap: 10px; }
                    .example h3 .badge { padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; }
                    .example h3 .badge.failed { background: #ffebee; color: #c62828; }
                    .example h3 .badge.passed { background: #e8f5e9; color: #2e7d32; }
                    .diff { font-family: 'Monaco', 'Menlo', 'Courier New', monospace; font-size: 12px; overflow-x: auto; }
                    .diff-line { white-space: pre; padding: 2px 8px; }
                    .diff-removed { background: #ffebee; color: #c62828; }
                    .diff-added { background: #e8f5e9; color: #2e7d32; }
                    .diff-unchanged { color: #666; }
                    .diff-header { background: #f5f5f5; color: #666; font-weight: bold; }
                    .stats { background: #fff3e0; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
                    .summary { background: %SUMMARY_BG%; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
                    pre { margin: 0; background: #263238; color: #aed581; padding: 15px; border-radius: 4px; overflow-x: auto; }

                    /* Tab styles */
                    .steps-tabs { margin-bottom: 20px; }
                    .steps-tabs h4 { margin: 0 0 15px 0; color: #333; font-size: 14px; }
                    .tab-bar { display: flex; flex-wrap: wrap; gap: 4px; border-bottom: 2px solid #e0e0e0; padding-bottom: 0; margin-bottom: 0; }
                    .tab-btn { padding: 8px 12px; border: 1px solid #e0e0e0; border-bottom: none; border-radius: 6px 6px 0 0; background: #fafafa; cursor: pointer; font-size: 12px; display: flex; flex-direction: column; align-items: center; gap: 4px; margin-bottom: -2px; transition: all 0.15s ease; min-width: 60px; }
                    .tab-btn:hover { background: #f0f0f0; }
                    .tab-btn.active { background: white; border-color: #e0e0e0; border-bottom: 2px solid white; font-weight: 500; }
                    .tab-btn.tab-failed { border-color: #ef5350; background: #ffebee; }
                    .tab-btn.tab-failed.active { background: white; border-bottom-color: white; }
                    .tab-header { display: flex; align-items: center; gap: 6px; }
                    .tab-number { background: #1976d2; color: white; width: 18px; height: 18px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: bold; }
                    .tab-btn.tab-failed .tab-number { background: #d32f2f; }
                    .tab-action { color: #333; font-size: 11px; }
                    .tab-input { font-family: 'Monaco', 'Menlo', 'Courier New', monospace; background: #e3f2fd; color: #1565c0; padding: 2px 6px; border-radius: 3px; font-size: 10px; }

                    .tab-content { display: none; border: 1px solid #e0e0e0; border-top: none; border-radius: 0 0 6px 6px; background: white; padding: 20px; }
                    .tab-content.active { display: block; }

                    .terminal-output { background: #1e1e1e; color: #d4d4d4; padding: 12px; border-radius: 4px; font-family: 'Monaco', 'Menlo', 'Courier New', monospace; font-size: 11px; white-space: pre; overflow: auto; line-height: 1.2; height: 400px; }
                    .output-label { margin: 0 0 8px 0; font-size: 13px; color: #666; }
                    .output-label.expected { color: #2e7d32; }
                    .output-label.actual { color: #d32f2f; }

                    .comparison { display: grid: grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px; }
                    .comparison-panel { }
                    .diff-section { padding-top: 15px; border-top: 1px solid #e0e0e0; }
                    .diff-section h4 { margin: 0 0 10px 0; font-size: 13px; color: #666; }
                </style>
            </head>
            <body>
                <h1>%TITLE_ICON% %TITLE%</h1>
                <div class="summary">
                    <strong>Results:</strong> %PASSED_STEPS%/%TOTAL_STEPS% steps passed
                    (%SUCCESS_COUNT% examples passed, %FAILURE_COUNT% failed)
                </div>
            HTML;
        $html = str_replace(
            ['%TITLE%', '%TITLE_COLOR%', '%TITLE_ICON%', '%SUMMARY_BG%', '%PASSED_STEPS%', '%TOTAL_STEPS%', '%SUCCESS_COUNT%', '%FAILURE_COUNT%'],
            [$title, $titleColor, $titleIcon, $summaryBg, (string) $passedSteps, (string) $totalSteps, (string) $successCount, (string) $failureCount],
            $html
        );

        // Failed examples section
        if ($failures) {
            $html .= '<h2 class="section-title">❌ Failed Examples ('.\count($failures).')</h2>';

            $failureIndex = 0;
            foreach ($failures as $name => $data) {
                $diff = self::compare($data['expected'], $data['actual']);
                $tabIdPrefix = 'failure'.$failureIndex;

                $html .= '<div class="example failed">';
                $html .= '<h3><span class="badge failed">FAILED</span> '.htmlspecialchars($data['example'] ?? $name).'.php</h3>';

                // Show all steps as tabs
                if (isset($data['all_steps']) && $data['all_steps']) {
                    $html .= '<div class="steps-tabs">';
                    $html .= '<h3>Steps to reproduce:</h3>';

                    // Tab bar
                    $html .= '<div class="tab-bar">';
                    $stepCount = \count($data['all_steps']);
                    foreach ($data['all_steps'] as $index => $step) {
                        $isFailedStep = $index === $stepCount - 1;
                        $tabClass = 'tab-btn'.($isFailedStep ? ' tab-failed' : '').(0 === $index ? ' active' : '');
                        $tabId = $tabIdPrefix.'_step'.$index;

                        $html .= '<button class="'.$tabClass.'" onclick="showTab(\''.$tabIdPrefix.'\', '.$index.')" id="'.$tabId.'_btn">';
                        $html .= '<span class="tab-header">';
                        $html .= '<span class="tab-number">'.($index + 1).'</span>';
                        if ('' !== $step['input']) {
                            $html .= '<span class="tab-input">'.htmlspecialchars($step['input']).'</span>';
                        }
                        $html .= '</span>';
                        $html .= '<span class="tab-action">'.htmlspecialchars($step['action']).'</span>';
                        $html .= '</button>';
                    }
                    $html .= '</div>';

                    // Tab contents
                    foreach ($data['all_steps'] as $index => $step) {
                        $isFailedStep = $index === $stepCount - 1;
                        $tabId = $tabIdPrefix.'_step'.$index;
                        $contentClass = 'tab-content'.(0 === $index ? ' active' : '');

                        $html .= '<div class="'.$contentClass.'" id="'.$tabId.'_content">';

                        if ($isFailedStep) {
                            // For failed step, show expected vs actual side by side with colors
                            $html .= '<div class="comparison">';

                            $html .= '<div class="comparison-panel">';
                            $html .= '<h4 class="output-label expected">✓ Expected output:</h4>';
                            $html .= '<div class="terminal-output">'.self::renderToHtml($data['expected_raw'] ?? $data['expected']).'</div>';
                            $html .= '</div>';

                            $html .= '<div class="comparison-panel">';
                            $html .= '<h4 class="output-label actual">✗ Actual output:</h4>';
                            $html .= '<div class="terminal-output">'.self::renderToHtml($data['actual_raw'] ?? $data['actual']).'</div>';
                            $html .= '</div>';

                            $html .= '</div>';

                            // Show diff below
                            $html .= '<div class="diff-section">';
                            $html .= '<h4>Diff ('.$diff['summary'].'):</h4>';
                            $html .= '<div class="diff">';

                            foreach ($diff['diff_lines'] as $line) {
                                $class = 'diff-'.$line['type'];
                                $prefix = match ($line['type']) {
                                    'added' => '+',
                                    'removed' => '-',
                                    'changed' => '~',
                                    default => ' ',
                                };
                                $content = htmlspecialchars($line['content']);
                                $html .= "<div class=\"diff-line {$class}\">{$prefix} {$content}</div>";

                                if ('changed' === $line['type'] && \array_key_exists('new_content', $line)) {
                                    $newContent = htmlspecialchars((string) $line['new_content']);
                                    $html .= "<div class=\"diff-line diff-added\">+ {$newContent}</div>";
                                }
                            }

                            $html .= '</div>';
                            $html .= '</div>';
                        } else {
                            // For passing steps, show expected output rendered through terminal emulator with colors
                            if (isset($step['output_raw'])) {
                                $expectedHtml = self::renderToHtml(base64_decode($step['output_raw']));
                            } else {
                                $expectedHtml = '(no output recorded)';
                            }
                            $html .= '<h4 class="output-label">Expected output after this step:</h4>';
                            $html .= '<div class="terminal-output">'.$expectedHtml.'</div>';
                        }

                        $html .= '</div>'; // tab-content
                    }

                    $html .= '</div>'; // steps-tabs
                } else {
                    // Fallback for old format without all_steps
                    $html .= '<p><strong>Step:</strong> '.htmlspecialchars($data['step']).'</p>';
                    $html .= '<div class="stats">'.$diff['summary'].'</div>';
                    $html .= '<div class="diff">';

                    foreach ($diff['diff_lines'] as $line) {
                        $class = 'diff-'.$line['type'];
                        $prefix = match ($line['type']) {
                            'added' => '+',
                            'removed' => '-',
                            'changed' => '~',
                            default => ' ',
                        };
                        $content = htmlspecialchars($line['content']);
                        $html .= "<div class=\"diff-line {$class}\">{$prefix} {$content}</div>";

                        if ('changed' === $line['type'] && \array_key_exists('new_content', $line)) {
                            $newContent = htmlspecialchars((string) $line['new_content']);
                            $html .= "<div class=\"diff-line diff-added\">+ {$newContent}</div>";
                        }
                    }

                    $html .= '</div>';
                }

                $html .= '</div>'; // example failed
                ++$failureIndex;
            }
        } // end failures section

        // Passed examples section
        if ($successes) {
            $html .= '<h2 class="section-title">✅ Passed Examples ('.\count($successes).')</h2>';

            $successIndex = 0;
            foreach ($successes as $name => $data) {
                $tabIdPrefix = 'success'.$successIndex;
                $steps = $data['all_steps'];

                $html .= '<div class="example passed">';
                $html .= '<h3><span class="badge passed">PASSED</span> '.htmlspecialchars($data['example']).'.php <small style="color: #666; font-weight: normal;">('.\count($steps).' steps)</small></h3>';

                // Show all steps as tabs
                $html .= '<div class="steps-tabs">';
                $html .= '<h4>Steps:</h4>';

                // Tab bar
                $html .= '<div class="tab-bar">';
                foreach ($steps as $index => $step) {
                    $tabClass = 'tab-btn'.(0 === $index ? ' active' : '');
                    $tabId = $tabIdPrefix.'_step'.$index;

                    $html .= '<button class="'.$tabClass.'" onclick="showTab(\''.$tabIdPrefix.'\', '.$index.')" id="'.$tabId.'_btn">';
                    $html .= '<span class="tab-header">';
                    $html .= '<span class="tab-number">'.($index + 1).'</span>';
                    if ('' !== $step['input']) {
                        $html .= '<span class="tab-input">'.htmlspecialchars($step['input']).'</span>';
                    }
                    $html .= '</span>';
                    $html .= '<span class="tab-action">'.htmlspecialchars($step['action']).'</span>';
                    $html .= '</button>';
                }
                $html .= '</div>';

                // Tab contents
                foreach ($steps as $index => $step) {
                    $tabId = $tabIdPrefix.'_step'.$index;
                    $contentClass = 'tab-content'.(0 === $index ? ' active' : '');

                    $html .= '<div class="'.$contentClass.'" id="'.$tabId.'_content">';

                    if (isset($step['output_raw'])) {
                        $expectedHtml = self::renderToHtml(base64_decode($step['output_raw']));
                    } else {
                        $expectedHtml = '(no output recorded)';
                    }
                    $html .= '<h4 class="output-label">Output after this step:</h4>';
                    $html .= '<div class="terminal-output">'.$expectedHtml.'</div>';

                    $html .= '</div>'; // tab-content
                }

                $html .= '</div>'; // steps-tabs
                $html .= '</div>'; // example passed
                ++$successIndex;
            }
        }

        // Add JavaScript for tab switching
        $html .= <<<'HTML'
            <script>
            function showTab(prefix, index) {
                // Hide all tab contents for this failure
                document.querySelectorAll('[id^="' + prefix + '_step"][id$="_content"]').forEach(el => {
                    el.classList.remove('active');
                });
                // Deactivate all tab buttons for this failure
                document.querySelectorAll('[id^="' + prefix + '_step"][id$="_btn"]').forEach(el => {
                    el.classList.remove('active');
                });
                // Show selected tab content and activate button
                document.getElementById(prefix + '_step' + index + '_content').classList.add('active');
                document.getElementById(prefix + '_step' + index + '_btn').classList.add('active');
            }
            </script>
            </body></html>
            HTML;

        return $html;
    }

    /**
     * Compute line-by-line diff using longest common subsequence.
     *
     * @param string[] $expected
     * @param string[] $actual
     *
     * @return list<array{type: string, line_num: int|null, content: string}>
     */
    private static function computeDiff(array $expected, array $actual): array
    {
        $m = \count($expected);
        $n = \count($actual);

        // Build LCS table
        $lcs = [];
        for ($i = 0; $i <= $m; ++$i) {
            $lcs[$i] = array_fill(0, $n + 1, 0);
        }

        for ($i = 1; $i <= $m; ++$i) {
            for ($j = 1; $j <= $n; ++$j) {
                if ($expected[$i - 1] === $actual[$j - 1]) {
                    $lcs[$i][$j] = $lcs[$i - 1][$j - 1] + 1;
                } else {
                    $lcs[$i][$j] = max($lcs[$i - 1][$j], $lcs[$i][$j - 1]);
                }
            }
        }

        // Backtrack to build diff
        $diff = [];
        $i = $m;
        $j = $n;

        while ($i > 0 || $j > 0) {
            if ($i > 0 && $j > 0 && $expected[$i - 1] === $actual[$j - 1]) {
                array_unshift($diff, [
                    'type' => 'unchanged',
                    'line_num' => $i,
                    'content' => $expected[$i - 1],
                ]);
                --$i;
                --$j;
            } elseif ($j > 0 && (0 === $i || $lcs[$i][$j - 1] >= $lcs[$i - 1][$j])) {
                array_unshift($diff, [
                    'type' => 'added',
                    'line_num' => $j,
                    'content' => $actual[$j - 1],
                ]);
                --$j;
            } elseif ($i > 0 && (0 === $j || $lcs[$i][$j - 1] < $lcs[$i - 1][$j])) {
                array_unshift($diff, [
                    'type' => 'removed',
                    'line_num' => $i,
                    'content' => $expected[$i - 1],
                ]);
                --$i;
            }
        }

        // Detect "changed" lines (adjacent removed + added)
        $result = [];
        $diffCount = \count($diff);

        for ($k = 0; $k < $diffCount; ++$k) {
            $current = $diff[$k];

            // Check for removed followed by added (marks as changed)
            if ('removed' === $current['type'] && $k + 1 < $diffCount && 'added' === $diff[$k + 1]['type']) {
                $result[] = [
                    'type' => 'changed',
                    'line_num' => $current['line_num'],
                    'content' => $current['content'],
                    'new_content' => $diff[$k + 1]['content'],
                ];
                ++$k; // Skip the next 'added' line
            } else {
                $result[] = $current;
            }
        }

        return $result;
    }

    /**
     * Compute statistics from diff.
     *
     * @param list<array{type: string, line_num: int|null, content: string, new_content?: string}> $diff
     *
     * @return array{added: int, removed: int, changed: int, unchanged: int}
     */
    private static function computeStats(array $diff): array
    {
        $stats = ['added' => 0, 'removed' => 0, 'changed' => 0, 'unchanged' => 0];

        foreach ($diff as $line) {
            if (isset($stats[$line['type']])) {
                ++$stats[$line['type']];
            }
        }

        return $stats;
    }

    /**
     * Format diff as unified diff text.
     *
     * @param list<array{type: string, line_num: int|null, content: string, new_content?: string}> $diff
     * @param string[]                                                                             $expected
     * @param string[]                                                                             $actual
     */
    private static function formatUnifiedDiff(array $diff, array $expected, array $actual): string
    {
        $lines = [];
        $lines[] = '--- expected';
        $lines[] = '+++ actual';
        $lines[] = \sprintf('@@ -%d,%d +%d,%d @@', 1, \count($expected), 1, \count($actual));

        foreach ($diff as $entry) {
            switch ($entry['type']) {
                case 'unchanged':
                    $lines[] = ' '.$entry['content'];
                    break;
                case 'removed':
                    $lines[] = '-'.$entry['content'];
                    break;
                case 'added':
                    $lines[] = '+'.$entry['content'];
                    break;
                case 'changed':
                    $lines[] = '-'.$entry['content'];
                    if (isset($entry['new_content'])) {
                        $lines[] = '+'.$entry['new_content'];
                    }
                    break;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Render terminal output to HTML with colors preserved.
     */
    private static function renderToHtml(string $output, int $width = 80): string
    {
        // Calculate height based on content - count newlines and add buffer
        // Terminal output may use cursor positioning, so we need enough height
        $lineCount = substr_count($output, "\n") + 1;
        $height = max(24, $lineCount + 5); // At least 24 lines, plus buffer for cursor movements

        $screen = new ScreenBuffer($width, $height);
        $screen->write($output);

        return new ScreenBufferHtmlRenderer()->convert($screen);
    }
}
