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

use PHPUnit\Framework\Assert;

final class AnsiSnapshot
{
    public static function assertSameWithColorRounding(string $expected, string $actual): void
    {
        $expectedColors = $actualColors = [];
        Assert::assertSame(self::withoutColors($expected, $expectedColors), self::withoutColors($actual, $actualColors));
        foreach ($expectedColors as $index => $channel) {
            Assert::assertEqualsWithDelta($channel, $actualColors[$index], 1, 'GD resampling may round a color channel by one.');
        }
    }

    private static function withoutColors(string $snapshot, array &$colors): string
    {
        return preg_replace_callback('/⟨([0-9;]*)m/', static function (array $match) use (&$colors): string {
            return '⟨'.preg_replace_callback('/(^|;)(38|48);2;(\d+);(\d+);(\d+)(?=;|$)/', static function (array $match) use (&$colors): string {
                array_push($colors, (int) $match[3], (int) $match[4], (int) $match[5]);

                return $match[1].$match[2].';2;R;G;B';
            }, $match[1]).'m';
        }, $snapshot);
    }
}
