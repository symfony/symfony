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
            [0,      '< 1 sec'],
            [1,      '1 sec'],
            [2,      '2 secs'],
            [59,     '59 secs'],
            [60,     '1 min'],
            [61,     '1 min'],
            [119,    '1 min'],
            [120,    '2 mins'],
            [121,    '2 mins'],
            [3599,   '59 mins'],
            [3600,   '1 hr'],
            [7199,   '1 hr'],
            [7200,   '2 hrs'],
            [7201,   '2 hrs'],
            [86399,  '23 hrs'],
            [86400,  '1 day'],
            [86401,  '1 day'],
            [172799, '1 day'],
            [172800, '2 days'],
            [172801, '2 days'],
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
     *
     * @param int    $secs
     * @param string $expectedFormat
     */
    public function testFormatTime($secs, $expectedFormat)
    {
        $this->assertEquals($expectedFormat, Helper::formatTime($secs));
    }

    /**
     * @dataProvider decoratedTextProvider
     */
    public function testRemoveDecoration(string $decoratedText, string $undecoratedText)
    {
        $this->assertEquals($undecoratedText, Helper::removeDecoration(new OutputFormatter(), $decoratedText));
    }
}
