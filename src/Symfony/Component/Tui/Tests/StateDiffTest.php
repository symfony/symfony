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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StateDiffTest extends TestCase
{
    public function testIdenticalOutputs()
    {
        $output = "Line 1\nLine 2\nLine 3";

        $result = StateDiff::compare($output, $output);

        $this->assertTrue($result['identical']);
        $this->assertSame('Outputs are identical', $result['summary']);
        $this->assertSame([], $result['diff_lines']);
        $this->assertSame('', $result['diff_text']);
        $this->assertSame(['added' => 0, 'removed' => 0, 'changed' => 0, 'unchanged' => 0], $result['stats']);
    }

    public function testAddedLines()
    {
        $expected = "Line 1\nLine 2";
        $actual = "Line 1\nLine 2\nLine 3";

        $result = StateDiff::compare($expected, $actual);

        $this->assertFalse($result['identical']);
        $this->assertSame(1, $result['stats']['added']);
        $this->assertSame(0, $result['stats']['removed']);
        $this->assertStringContainsString('+Line 3', $result['diff_text']);
    }

    public function testRemovedLines()
    {
        $expected = "Line 1\nLine 2\nLine 3";
        $actual = "Line 1\nLine 2";

        $result = StateDiff::compare($expected, $actual);

        $this->assertFalse($result['identical']);
        $this->assertSame(0, $result['stats']['added']);
        $this->assertSame(1, $result['stats']['removed']);
        $this->assertStringContainsString('-Line 3', $result['diff_text']);
    }

    public function testChangedLines()
    {
        $expected = "Line 1\nOld Line\nLine 3";
        $actual = "Line 1\nNew Line\nLine 3";

        $result = StateDiff::compare($expected, $actual);

        $this->assertFalse($result['identical']);
        $this->assertSame(1, $result['stats']['changed']);
        $this->assertStringContainsString('-Old Line', $result['diff_text']);
        $this->assertStringContainsString('+New Line', $result['diff_text']);
    }

    public function testComplexDiff()
    {
        $expected = "Header\nLine A\nLine B\nLine C\nFooter";
        $actual = "Header\nLine A\nLine B Modified\nLine D\nFooter";

        $result = StateDiff::compare($expected, $actual);

        $this->assertFalse($result['identical']);
        // Changed: Line B -> Line B Modified
        // Changed: Line C -> Line D
        $this->assertGreaterThan(0, $result['stats']['changed'] + $result['stats']['added'] + $result['stats']['removed']);
    }

    public function testSideBySide()
    {
        $expected = "Line 1\nLine 2";
        $actual = "Line 1\nDifferent";

        $result = StateDiff::sideBySide($expected, $actual, 60);

        $this->assertStringContainsString('Expected', $result);
        $this->assertStringContainsString('Actual', $result);
        $this->assertStringContainsString('Line 1', $result);
        $this->assertStringContainsString('Different', $result);
    }

    public function testHtmlReport()
    {
        $failures = [
            'test_step01' => [
                'expected' => "Line 1\nLine 2",
                'actual' => "Line 1\nModified",
                'step' => 'initial',
            ],
        ];

        $html = StateDiff::generateHtmlReport($failures, [], 2, 1, 'Test Report');

        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('Test Report', $html);
        $this->assertStringContainsString('test_step01', $html);
        $this->assertStringContainsString('diff-changed', $html);
        $this->assertStringContainsString('1/2 steps passed', $html);
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function emptyInputProvider(): iterable
    {
        yield 'both empty' => ['', '', true];
        yield 'empty expected' => ['', "Line 1\nLine 2", false];
        yield 'empty actual' => ["Line 1\nLine 2", '', false];
    }

    #[DataProvider('emptyInputProvider')]
    public function testEmptyInputEdgeCases(string $expected, string $actual, bool $identical)
    {
        $result = StateDiff::compare($expected, $actual);

        $this->assertSame($identical, $result['identical']);
    }

    public function testUnifiedDiffFormat()
    {
        $expected = "A\nB\nC";
        $actual = "A\nX\nC";

        $result = StateDiff::compare($expected, $actual);

        $this->assertStringContainsString('--- expected', $result['diff_text']);
        $this->assertStringContainsString('+++ actual', $result['diff_text']);
        $this->assertStringContainsString('@@', $result['diff_text']);
    }
}
