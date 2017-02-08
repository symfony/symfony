<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\String\Tests;

use Symfony\Component\String\AbstractString;
use Symfony\Component\String\GraphemeString;

class GraphemeStringTest extends AbstractUtf8TestCase
{
    protected static function createFromString(string $string): AbstractString
    {
        return new GraphemeString($string);
    }

    public static function provideLength(): array
    {
        return array_merge(
            parent::provideLength(),
            [
                // 5 letters + 3 combining marks
                [5, 'अनुच्छेद'],
            ]
        );
    }

    public static function provideSplit(): array
    {
        return array_merge(
            parent::provideSplit(),
            [
                [
                    'अ.नु.च्.छे.द',
                    '.',
                    [
                        static::createFromString('अ'),
                        static::createFromString('नु'),
                        static::createFromString('च्'),
                        static::createFromString('छे'),
                        static::createFromString('द'),
                    ],
                    null,
                ],
            ]
        );
    }

    public static function provideChunk(): array
    {
        return array_merge(
            parent::provideChunk(),
            [
                [
                    'अनुच्छेद',
                    [
                        static::createFromString('अ'),
                        static::createFromString('नु'),
                        static::createFromString('च्'),
                        static::createFromString('छे'),
                        static::createFromString('द'),
                    ],
                    1,
                ],
            ]
        );
    }

    public static function provideLower(): array
    {
        return array_merge(
            parent::provideLower(),
            [
                // Hindi
                ['अनुच्छेद', 'अनुच्छेद'],
            ]
        );
    }

    public static function provideUpper(): array
    {
        return array_merge(
            parent::provideUpper(),
            [
                // Hindi
                ['अनुच्छेद', 'अनुच्छेद'],
            ]
        );
    }

    public static function provideAppend(): array
    {
        return array_merge(
            parent::provideAppend(),
            [
                [
                    'तद्भव देशज',
                    ['तद्भव', ' ', 'देशज'],
                ],
                [
                    'तद्भव देशज विदेशी',
                    ['तद्भव', ' देशज', ' विदेशी'],
                ],
            ]
        );
    }

    public static function providePrepend(): array
    {
        return array_merge(
            parent::providePrepend(),
            [
                [
                    'देशज तद्भव',
                    ['तद्भव', 'देशज '],
                ],
                [
                    'विदेशी देशज तद्भव',
                    ['तद्भव', 'देशज ', 'विदेशी '],
                ],
            ]
        );
    }

    public static function provideBeforeAfter(): array
    {
        return array_merge(
            parent::provideBeforeAfter(),
            [
                ['द foo अनुच्छेद', 'द', 'अनुच्छेद foo अनुच्छेद', false],
                ['अनुच्छे', 'द', 'अनुच्छेद foo अनुच्छेद', true],
            ]
        );
    }

    public static function provideBeforeAfterIgnoreCase(): array
    {
        return array_merge(
            parent::provideBeforeAfterIgnoreCase(),
            [
                ['', 'छेछे', 'दछेच्नुअ', false],
                ['', 'छेछे', 'दछेच्नुअ', true],
                ['छेच्नुअ', 'छे', 'दछेच्नुअ', false],
                ['द', 'छे', 'दछेच्नुअ', true],
            ]
        );
    }

    public static function provideBeforeAfterLast(): array
    {
        return array_merge(
            parent::provideBeforeAfterLast(),
            [
                ['', 'छेछे', 'दछेच्नुअ-दछेच्नु-अदछेच्नु', false],
                ['', 'छेछे', 'दछेच्नुअ-दछेच्नु-अदछेच्नु', true],
                ['-दछेच्नु', '-द', 'दछेच्नुअ-दछेच्नु-अद-दछेच्नु', false],
                ['दछेच्नुअ-दछेच्नु-अद', '-द', 'दछेच्नुअ-दछेच्नु-अद-दछेच्नु', true],
            ]
        );
    }

    public static function provideBeforeAfterLastIgnoreCase(): array
    {
        return array_merge(
            parent::provideBeforeAfterLastIgnoreCase(),
            [
                ['', 'छेछे', 'दछेच्नुअ-दछेच्नु-अदछेच्नु', false],
                ['', 'छेछे', 'दछेच्नुअ-दछेच्नु-अदछेच्नु', true],
                ['-दछेच्नु', '-द', 'दछेच्नुअ-दछेच्नु-अद-दछेच्नु', false],
                ['दछेच्नुअ-दछेच्नु-अद', '-द', 'दछेच्नुअ-दछेच्नु-अद-दछेच्नु', true],
            ]
        );
    }

    public static function provideReplace(): array
    {
        return array_merge(
            parent::provideReplace(),
            [
                ['Das Innenministerium', 1, 'Das Außenministerium', 'Auß', 'Inn'],
                ['दछेच्नुद-दछेच्नु-ददछेच्नु', 2, 'दछेच्नुअ-दछेच्नु-अदछेच्नु', 'अ', 'द'],
            ]
        );
    }

    public static function provideReplaceIgnoreCase(): array
    {
        return array_merge(
            parent::provideReplaceIgnoreCase(),
            [
                ['Das Aussenministerium', 1, 'Das Außenministerium', 'auß', 'Auss'],
                ['दछेच्नुद-दछेच्नु-ददछेच्नु', 2, 'दछेच्नुअ-दछेच्नु-अदछेच्नु', 'अ', 'द'],
            ]
        );
    }
}
