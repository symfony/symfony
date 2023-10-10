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
            [0,      '< 1 sec', 1],
            [0.95,   '< 1 sec', 1],
            [1,      '1 sec', 1],
            [2,      '2 secs', 2],
            [59,     '59 secs', 1],
            [59.21,  '59 secs', 1],
            [60,     '1 min', 2],
            [61,     '1 min, 1 sec', 2],
            [119,    '1 min, 59 secs', 2],
            [120,    '2 mins', 2],
            [121,    '2 mins, 1 sec', 2],
            [3599,   '59 mins, 59 secs', 2],
            [3600,   '1 hr', 2],
            [7199,   '1 hr, 59 mins', 2],
            [7200,   '2 hrs', 2],
            [7201,   '2 hrs', 2],
            [86399,  '23 hrs, 59 mins', 2],
            [86399,  '23 hrs, 59 mins, 59 secs', 3],
            [86400,  '1 day', 2],
            [86401,  '1 day', 2],
            [172799, '1 day, 23 hrs', 2],
            [172799, '1 day, 23 hrs, 59 mins, 59 secs', 4],
            [172800, '2 days', 2],
            [172801, '2 days', 2],
            [172801, '2 days, 1 sec', 4],
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
