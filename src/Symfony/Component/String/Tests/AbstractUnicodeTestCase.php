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

use Symfony\Component\String\Exception\InvalidArgumentException;

abstract class AbstractUnicodeTestCase extends AbstractAsciiTestCase
{
    public static function provideWidth(): array
    {
        return array_merge(
            parent::provideWidth(),
            [
                [14, '<<<END
This is a
multiline text
END'],
            ]
        );
    }

    public function testCreateFromStringWithInvalidUtf8Input()
    {
        self::expectException(InvalidArgumentException::class);

        self::createFromString("\xE9");
    }

    public function testAscii()
    {
        $s = self::createFromString('Dieser Wert sollte größer oder gleich');
        self::assertSame('Dieser Wert sollte grosser oder gleich', (string) $s->ascii());
        self::assertSame('Dieser Wert sollte groesser oder gleich', (string) $s->ascii(['de-ASCII']));
    }

    public function testAsciiClosureRule()
    {
        $rule = function ($c) {
            return str_replace('ö', 'OE', $c);
        };

        $s = self::createFromString('Dieser Wert sollte größer oder gleich');
        self::assertSame('Dieser Wert sollte grOEsser oder gleich', (string) $s->ascii([$rule]));
    }

    public function provideCreateFromCodePoint(): array
    {
        return [
            ['', []],
            ['*', [42]],
            ['AZ', [65, 90]],
            ['€', [8364]],
            ['€', [0x20AC]],
            ['Ʃ', [425]],
            ['Ʃ', [0x1A9]],
            ['☢☎❄', [0x2622, 0x260E, 0x2744]],
        ];
    }

    public static function provideBytesAt(): array
    {
        return array_merge(
            parent::provideBytesAt(),
            [
                [[0xC3, 0xA4], 'Späßchen', 2],
                [[0xC3, 0x9F], 'Späßchen', -5],
            ]
        );
    }

    /**
     * @dataProvider provideCodePointsAt
     */
    public function testCodePointsAt(array $expected, string $string, int $offset, int $form = null)
    {
        if (2 !== grapheme_strlen('च्छे') && 'नमस्ते' === $string) {
            self::markTestSkipped('Skipping due to issue ICU-21661.');
        }

        $instance = self::createFromString($string);
        $instance = $form ? $instance->normalize($form) : $instance;

        self::assertSame($expected, $instance->codePointsAt($offset));
    }

    public static function provideCodePointsAt(): array
    {
        return [
            [[], '', 0],
            [[], 'a', 1],
            [[0x53], 'Späßchen', 0],
            [[0xE4], 'Späßchen', 2],
            [[0xDF], 'Späßchen', -5],
            [[0x260E], '☢☎❄', 1],
        ];
    }

    public static function provideLength(): array
    {
        return [
            [1, 'a'],
            [1, 'ß'],
            [2, 'is'],
            [3, 'PHP'],
            [3, '한국어'],
            [4, 'Java'],
            [7, 'Symfony'],
            [10, 'pineapples'],
            [22, 'Symfony is super cool!'],
        ];
    }

    public static function provideIndexOf(): array
    {
        return array_merge(
            parent::provideIndexOf(),
            [
                [1, '한국어', '국', 0],
                [1, '한국어', '국', 1],
                [null, '한국어', '국', 2],
                [8, 'der Straße nach Paris', 'ß', 4],
            ]
        );
    }

    public static function provideIndexOfIgnoreCase(): array
    {
        return array_merge(
            parent::provideIndexOfIgnoreCase(),
            [
                [3, 'DÉJÀ', 'À', 0],
                [3, 'DÉJÀ', 'à', 0],
                [1, 'DÉJÀ', 'É', 1],
                [1, 'DÉJÀ', 'é', 1],
                [1, 'aςσb', 'ΣΣ', 0],
                [16, 'der Straße nach Paris', 'Paris', 0],
                [8, 'der Straße nach Paris', 'ß', 4],
            ]
        );
    }

    public static function provideIndexOfLast(): array
    {
        return array_merge(
            parent::provideIndexOfLast(),
            [
                [null, '한국어', '', 0],
                [1, '한국어', '국', 0],
                [5, '한국어어어어국국', '어', 0],
                // see https://bugs.php.net/bug.php?id=74264
                [15, 'abcdéf12é45abcdéf', 'é', 0],
                [8, 'abcdéf12é45abcdéf', 'é', -4],
            ]
        );
    }

    public static function provideIndexOfLastIgnoreCase(): array
    {
        return array_merge(
            parent::provideIndexOfLastIgnoreCase(),
            [
                [null, '한국어', '', 0],
                [3, 'DÉJÀ', 'à', 0],
                [3, 'DÉJÀ', 'À', 0],
                [6, 'DÉJÀÀÀÀ', 'à', 0],
                [6, 'DÉJÀÀÀÀ', 'à', 3],
                [5, 'DÉJÀÀÀÀ', 'àà', 0],
                [2, 'DÉJÀÀÀÀ', 'jà', 0],
                [2, 'DÉJÀÀÀÀ', 'jà', -5],
                [6, 'DÉJÀÀÀÀ!', 'à', -2],
                // see https://bugs.php.net/bug.php?id=74264
                [5, 'DÉJÀÀÀÀ', 'à', -2],
                [15, 'abcdéf12é45abcdéf', 'é', 0],
                [8, 'abcdéf12é45abcdéf', 'é', -4],
                [1, 'aςσb', 'ΣΣ', 0],
            ]
        );
    }

    public static function provideSplit(): array
    {
        return array_merge(
            parent::provideSplit(),
            [
                [
                    '會|意|文|字|/|会|意|文|字',
                    '|',
                    [
                        self::createFromString('會'),
                        self::createFromString('意'),
                        self::createFromString('文'),
                        self::createFromString('字'),
                        self::createFromString('/'),
                        self::createFromString('会'),
                        self::createFromString('意'),
                        self::createFromString('文'),
                        self::createFromString('字'),
                    ],
                    null,
                ],
                [
                    '會|意|文|字|/|会|意|文|字',
                    '|',
                    [
                        self::createFromString('會'),
                        self::createFromString('意'),
                        self::createFromString('文'),
                        self::createFromString('字'),
                        self::createFromString('/|会|意|文|字'),
                    ],
                    5,
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
                    'déjà',
                    [
                        self::createFromString('d'),
                        self::createFromString('é'),
                        self::createFromString('j'),
                        self::createFromString('à'),
                    ],
                    1,
                ],
                [
                    'déjà',
                    [
                        self::createFromString('dé'),
                        self::createFromString('jà'),
                    ],
                    2,
                ],
            ]
        );
    }

    public function testTrimWithInvalidUtf8CharList()
    {
        self::expectException(InvalidArgumentException::class);

        self::createFromString('Symfony')->trim("\xE9");
    }

    public function testTrimStartWithInvalidUtf8CharList()
    {
        self::expectException(InvalidArgumentException::class);

        self::createFromString('Symfony')->trimStart("\xE9");
    }

    public function testTrimEndWithInvalidUtf8CharList()
    {
        self::expectException(InvalidArgumentException::class);

        self::createFromString('Symfony')->trimEnd("\xE9");
    }

    public static function provideLower(): array
    {
        return array_merge(
            parent::provideLower(),
            [
                // French
                ['garçon', 'garçon'],
                ['garçon', 'GARÇON'],
                ["œuvre d'art", "Œuvre d'Art"],

                // Spanish
                ['el niño', 'El Niño'],

                // Romanian
                ['împărat', 'Împărat'],

                // Random symbols
                ['déjà σσς i̇iıi', 'DÉJÀ Σσς İIıi'],
            ]
        );
    }

    public static function provideUpper(): array
    {
        return array_merge(
            parent::provideUpper(),
            [
                // French
                ['GARÇON', 'garçon'],
                ['GARÇON', 'GARÇON'],
                ["ŒUVRE D'ART", "Œuvre d'Art"],

                // German
                ['ÄUSSERST', 'äußerst'],

                // Spanish
                ['EL NIÑO', 'El Niño'],

                // Romanian
                ['ÎMPĂRAT', 'Împărat'],

                // Random symbols
                ['DÉJÀ ΣΣΣ İIII', 'Déjà Σσς İIıi'],
            ]
        );
    }

    public static function provideTitle(): array
    {
        return array_merge(
            parent::provideTitle(),
            [
                ['Deja', 'deja', false],
                ['Σσς', 'σσς', false],
                ['DEJa', 'dEJa', false],
                ['ΣσΣ', 'σσΣ', false],
                ['Deja Σσς DEJa ΣσΣ', 'deja σσς dEJa σσΣ', true],

                // Spanish
                ['Última prueba', 'última prueba', false],
                ['ÚLTIMA pRUEBA', 'úLTIMA pRUEBA', false],

                ['¡Hola spain!', '¡hola spain!', false],
                ['¡HOLA sPAIN!', '¡hOLA sPAIN!', false],

                ['¡Hola Spain!', '¡hola spain!', true],
                ['¡HOLA SPAIN!', '¡hOLA sPAIN!', true],

                ['Última Prueba', 'última prueba', true],
                ['ÚLTIMA PRUEBA', 'úLTIMA pRUEBA', true],
            ]
        );
    }

    public static function provideSlice(): array
    {
        return array_merge(
            parent::provideSlice(),
            [
                ['jà', 'déjà', 2, null],
                ['jà', 'déjà', 2, null],
                ['jà', 'déjà', -2, null],
                ['jà', 'déjà', -2, 3],
                ['', 'déjà', -1, 0],
                ['', 'déjà', 1, -4],
                ['j', 'déjà', -2, -1],
                ['', 'déjà', -2, -2],
                ['', 'déjà', 5, 0],
                ['', 'déjà', -5, 0],
            ]
        );
    }

    public static function provideAppend(): array
    {
        return array_merge(
            parent::provideAppend(),
            [
                [
                    'Déjà Σσς',
                    ['Déjà', ' ', 'Σσς'],
                ],
                [
                    'Déjà Σσς İIıi',
                    ['Déjà', ' Σσς', ' İIıi'],
                ],
            ]
        );
    }

    public function testAppendInvalidUtf8String()
    {
        self::expectException(InvalidArgumentException::class);

        self::createFromString('Symfony')->append("\xE9");
    }

    public static function providePrepend(): array
    {
        return array_merge(
            parent::providePrepend(),
            [
                [
                    'Σσς Déjà',
                    ['Déjà', 'Σσς '],
                ],
                [
                    'İIıi Σσς Déjà',
                    ['Déjà', 'Σσς ', 'İIıi '],
                ],
            ]
        );
    }

    public function testPrependInvalidUtf8String()
    {
        self::expectException(InvalidArgumentException::class);

        self::createFromString('Symfony')->prepend("\xE9");
    }

    public static function provideBeforeAfter(): array
    {
        return array_merge(
            parent::provideBeforeAfter(),
            [
                ['jàdéjà', 'jà', 'déjàdéjà', 0, false],
                ['dé', 'jà', 'déjàdéjà', 0, true],
            ]
        );
    }

    public static function provideBeforeAfterIgnoreCase(): array
    {
        return array_merge(
            parent::provideBeforeAfterIgnoreCase(),
            [
                ['jàdéjà', 'JÀ', 'déjàdéjà', 0, false],
                ['dé', 'jÀ', 'déjàdéjà', 0, true],
                ['éjàdéjà', 'é', 'déjàdéjà', 0, false],
                ['d', 'é', 'déjàdéjà', 0, true],
                ['déjàdéjà', 'Ç', 'déjàdéjà', 0, false],
                ['déjàdéjà', 'Ç', 'déjàdéjà', 0, true],
            ]
        );
    }

    public static function provideBeforeAfterLast(): array
    {
        return array_merge(
            parent::provideBeforeAfterLast(),
            [
                ['déjàdéjà', 'Ç', 'déjàdéjà', 0, false],
                ['déjàdéjà', 'Ç', 'déjàdéjà', 0, true],
                ['éjà', 'é', 'déjàdéjà', 0, false],
                ['déjàd', 'é', 'déjàdéjà', 0, true],
            ]
        );
    }

    public static function provideBeforeAfterLastIgnoreCase(): array
    {
        return array_merge(
            parent::provideBeforeAfterLastIgnoreCase(),
            [
                ['déjàdéjà', 'Ç', 'déjàdéjà', 0, false],
                ['éjà', 'é', 'déjàdéjà', 0, false],
                ['éjà', 'É', 'déjàdéjà', 0, false],
            ]
        );
    }

    public static function provideToFoldedCase(): array
    {
        return array_merge(
            parent::provideToFoldedCase(),
            [
                ['déjà', 'DéjÀ'],
                ['σσσ', 'Σσς'],
                ['iıi̇i', 'Iıİi'],
            ]
        );
    }

    public static function provideReplace(): array
    {
        return array_merge(
            parent::provideReplace(),
            [
                ['ΣσΣ', 1, 'Σσς', 'ς', 'Σ'],
                ['漢字はユニコード', 0, '漢字はユニコード', 'foo', 'bar'],
                ['漢字ーユニコード', 1, '漢字はユニコード', 'は', 'ー'],
                ['This is a jamais-vu situation!', 1, 'This is a déjà-vu situation!', 'déjà', 'jamais'],
            ]
        );
    }

    public static function provideReplaceMatches(): array
    {
        return array_merge(
            parent::provideReplaceMatches(),
            [
                ['This is a dj-vu situation!', 'This is a déjà-vu situation!', '/([à-ú])/', ''],
            ]
        );
    }

    public static function provideReplaceIgnoreCase(): array
    {
        return array_merge(
            parent::provideReplaceIgnoreCase(),
            [
                // σ and ς are lowercase variants for Σ
                ['ΣΣΣ', 3, 'σσσ', 'σ', 'Σ'],
                ['ΣΣΣ', 3, 'σσσ', 'ς', 'Σ'],
                ['Σσ', 1, 'σσσ', 'σσ', 'Σ'],
                ['漢字はユニコード', 0, '漢字はユニコード', 'foo', 'bar'],
                ['漢字ーユニコード', 1, '漢字はユニコード', 'は', 'ー'],
                ['This is a jamais-vu situation!', 1, 'This is a déjà-vu situation!', 'DÉjÀ', 'jamais'],
            ]
        );
    }

    public function testReplaceWithInvalidUtf8Pattern()
    {
        self::assertEquals('Symfony', self::createFromString('Symfony')->replace("\xE9", 'p'));
    }

    public function testReplaceWithInvalidUtf8PatternReplacement()
    {
        self::expectException(InvalidArgumentException::class);

        self::createFromString('Symfony')->replace('f', "\xE9");
    }

    public static function provideCamel()
    {
        return array_merge(
            parent::provideCamel(),
            [
                ['symfonyIstÄußerstCool', 'symfony_ist_äußerst_cool'],
            ]
        );
    }

    public static function provideSnake()
    {
        return array_merge(
            parent::provideSnake(),
            [
                ['symfony_ist_äußerst_cool', 'symfonyIstÄußerstCool'],
            ]
        );
    }

    public static function provideEqualsTo()
    {
        return array_merge(
            parent::provideEqualsTo(),
            [
                [true, 'äußerst', 'äußerst'],
                [false, 'BÄR', 'bär'],
                [false, 'Bär', 'Bar'],
            ]
        );
    }

    public static function provideEqualsToIgnoreCase()
    {
        return array_merge(
            parent::provideEqualsToIgnoreCase(),
            [
                [true, 'Äußerst', 'äußerst'],
                [false, 'Bär', 'Bar'],
            ]
        );
    }

    public static function providePadBoth(): array
    {
        return array_merge(
            parent::providePadBoth(),
            [
                ['äußerst', 'äußerst', 7, '+'],
                ['+äußerst+', 'äußerst', 9, '+'],
                ['äö.äöä', '.', 6, 'äö'],
            ]
        );
    }

    public static function providePadEnd(): array
    {
        return array_merge(
            parent::providePadEnd(),
            [
                ['äußerst', 'äußerst', 7, '+'],
                ['äußerst+', 'äußerst', 8, '+'],
                ['.äöä', '.', 4, 'äö'],
            ]
        );
    }

    public static function providePadStart(): array
    {
        return array_merge(
            parent::providePadStart(),
            [
                ['äußerst', 'äußerst', 7, '+'],
                ['+äußerst', 'äußerst', 8, '+'],
                ['äöä.', '.', 4, 'äö'],
            ]
        );
    }

    public static function provideReverse()
    {
        return array_merge(
            parent::provideReverse(),
            [
                ['äuß⭐erst', 'tsre⭐ßuä'],
                ['漢字ーユニコードéèΣσς', 'ςσΣèéドーコニユー字漢'],
                ['नमस्ते', 'तेस्मन'],
            ]
        );
    }
}
