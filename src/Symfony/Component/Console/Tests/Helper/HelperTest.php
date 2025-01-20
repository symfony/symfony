<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Console\Tests\Helper;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Helper\Helper;

class HelperTest extends TestCase
{
    public static function formatTimeProvider()
    {
        return [
            [0,      '< 1 ms', 1],
            [0.0004, '< 1 ms', 1],
            [0.95,   '950 ms', 1],
            [1,      '1 s', 1],
            [2,      '2 s', 2],
            [59,     '59 s', 1],
            [59.21,  '59 s', 1],
            [59.21,  '59 s, 210 ms', 5],
            [60,     '1 min', 2],
            [61,     '1 min, 1 s', 2],
            [119,    '1 min, 59 s', 2],
            [120,    '2 min', 2],
            [121,    '2 min, 1 s', 2],
            [3599,   '59 min, 59 s', 2],
            [3600,   '1 h', 2],
            [7199,   '1 h, 59 min', 2],
            [7200,   '2 h', 2],
            [7201,   '2 h', 2],
            [86399,  '23 h, 59 min', 2],
            [86399,  '23 h, 59 min, 59 s', 3],
            [86400,  '1 d', 2],
            [86401,  '1 d', 2],
            [172799, '1 d, 23 h', 2],
            [172799, '1 d, 23 h, 59 min, 59 s', 4],
            [172799.123, '1 d, 23 h, 59 min, 59 s, 123 ms', 5],
            [172800, '2 d', 2],
            [172801, '2 d', 2],
            [172801, '2 d, 1 s', 4],
        ];
    }

    public static function decoratedTextProvider()
    {
        return [
            ['abc', 'abc'],
            ['abc<fg=default;bg=default>', 'abc'],
            ["a\033[1;36mbc", 'abc'],
            ["a\033]8;;http://url\033\\b\033]8;;\033\\c", 'abc'],
        ];
    }

    /**
     * @dataProvider formatTimeProvider
     */
    public function testFormatTime(int|float $secs, string $expectedFormat, int $precision)
    {
        $this->assertEquals($expectedFormat, Helper::formatTime($secs, $precision));
    }

    /**
     * @dataProvider decoratedTextProvider
     */
    public function testRemoveDecoration(string $decoratedText, string $undecoratedText)
    {
        $this->assertEquals($undecoratedText, Helper::removeDecoration(new OutputFormatter(), $decoratedText));
    }
}
