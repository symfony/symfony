<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Compatibility;

use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AnsiSnapshotTest extends TestCase
{
    public function testAllowsOnlyOneValueOfRoundingPerColorChannel()
    {
        AnsiSnapshot::assertSameWithColorRounding('⟨1;38;2;30;41;59;48;2;140;123;40m▀⟨0m', '⟨1;38;2;29;42;59;48;2;140;122;41m▀⟨0m');
    }

    #[DataProvider('provideDifferentSnapshots')]
    public function testRejectsOtherDifferences(string $actual)
    {
        $this->expectException(AssertionFailedError::class);

        AnsiSnapshot::assertSameWithColorRounding('⟨38;2;30;41;59m▀⟨0m', $actual);
    }

    public static function provideDifferentSnapshots(): iterable
    {
        yield 'larger color change' => ['⟨38;2;28;41;59m▀⟨0m'];
        yield 'different glyph' => ['⟨38;2;30;41;59m▄⟨0m'];
        yield 'different spacing' => ['⟨38;2;30;41;59m▀ ⟨0m'];
        yield 'different rendition' => ['⟨1;38;2;30;41;59m▀⟨0m'];
        yield 'different color plane' => ['⟨48;2;30;41;59m▀⟨0m'];
        yield 'missing reset' => ['⟨38;2;30;41;59m▀'];
    }
}
