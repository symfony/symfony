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

use PHPUnit\Framework\TestCase;

final class TerminalCellSnapshotTest extends TestCase
{
    public function testEquivalentColorEncodingsAndRunBoundariesCompareEqually()
    {
        $expected = "⟨31mRed text⟨0m\n";
        $actual = "⟨38;2;205;0;0mRed⟨0m⟨38;5;1m text⟨0m\n";
        $this->assertSame(TerminalCellSnapshot::normalize($expected, 20, 2), TerminalCellSnapshot::normalize($actual, 20, 2));
    }

    public function testDefaultForegroundRequiresAnExplicitProfile()
    {
        $default = TerminalCellSnapshot::normalize("Text\n", 20, 2);
        $white = TerminalCellSnapshot::normalize("⟨38;2;255;255;255mText⟨0m\n", 20, 2);
        $this->assertSame($default, $white);
        $this->assertNotSame(TerminalCellSnapshot::normalize("Text\n", 20, 2, [229, 229, 229]), $white);
    }

    public function testDecorationBackgroundAndGlyphDifferencesRemainVisible()
    {
        $plain = TerminalCellSnapshot::normalize("A中é\n", 20, 2);
        $this->assertNotSame($plain, TerminalCellSnapshot::normalize("⟨4mA中é⟨0m\n", 20, 2));
        $this->assertNotSame($plain, TerminalCellSnapshot::normalize("⟨41mA中é⟨0m\n", 20, 2));
        $this->assertNotSame($plain, TerminalCellSnapshot::normalize("A文é\n", 20, 2));
        $this->assertSame(TerminalCellSnapshot::normalize(" \n", 20, 2), TerminalCellSnapshot::normalize("⟨31m ⟨0m\n", 20, 2));
        $this->assertSame(TerminalCellSnapshot::normalize(" \n", 20, 2), TerminalCellSnapshot::normalize("⟨2m ⟨0m\n", 20, 2));
    }

    public function testFinalNewlineDoesNotScrollTheFirstRowAway()
    {
        $cells = TerminalCellSnapshot::normalize("A\nB\n", 1, 2);
        $this->assertSame('A', $cells[0][0][0]);
        $this->assertSame('B', $cells[1][0][0]);
    }
}
