<?php

namespace Symfony\Component\String\Tests;

use Symfony\Component\String\Exception\InvalidArgumentException;

abstract class AbstractUtf8TestCase extends AbstractAsciiTestCase
{
    public function testCreateFromStringWithInvalidUtf8Input()
    {
        $this->expectException(InvalidArgumentException::class);

        static::createFromString("\xE9");
    }

    public function provideCreateFromCodePoint(): array
    {
        return [
            ['', []],
            ['*', [42]],
            ['AZ', [65, 90]],
            ['€', [8364]],
            ['€', [0x20ac]],
            ['Ʃ', [425]],
            ['Ʃ', [0x1a9]],
            ['☢☎❄', [0x2622, 0x260E, 0x2744]],
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
                        static::createFromString('會'),
                        static::createFromString('意'),
                        static::createFromString('文'),
                        static::createFromString('字'),
                        static::createFromString('/'),
                        static::createFromString('会'),
                        static::createFromString('意'),
                        static::createFromString('文'),
                        static::createFromString('字'),
                    ],
                    null,
                ],
                [
                    '會|意|文|字|/|会|意|文|字',
                    '|',
                    [
                        static::createFromString('會'),
                        static::createFromString('意'),
                        static::createFromString('文'),
                        static::createFromString('字'),
                        static::createFromString('/|会|意|文|字'),
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
                        static::createFromString('d'),
                        static::createFromString('é'),
                        static::createFromString('j'),
                        static::createFromString('à'),
                    ],
                    1,
                ],
                [
                    'déjà',
                    [
                        static::createFromString('dé'),
                        static::createFromString('jà'),
                    ],
                    2,
                ],
            ]
        );
    }

    public function testTrimWithInvalidUtf8CharList()
    {
        $this->expectException(InvalidArgumentException::class);

        static::createFromString('Symfony')->trim("\xE9");
    }

    public function testTrimStartWithInvalidUtf8CharList()
    {
        $this->expectException(InvalidArgumentException::class);

        static::createFromString('Symfony')->trimStart("\xE9");
    }

    public function testTrimEndWithInvalidUtf8CharList()
    {
        $this->expectException(InvalidArgumentException::class);

        static::createFromString('Symfony')->trimEnd("\xE9");
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
        $this->expectException(InvalidArgumentException::class);

        static::createFromString('Symfony')->append("\xE9");
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
        $this->expectException(InvalidArgumentException::class);

        static::createFromString('Symfony')->prepend("\xE9");
    }

    public static function provideBeforeAfter(): array
    {
        return array_merge(
            parent::provideBeforeAfter(),
            [
                ['jàdéjà', 'jà', 'déjàdéjà', false],
                ['dé', 'jà', 'déjàdéjà', true],
            ]
        );
    }

    public static function provideBeforeAfterIgnoreCase(): array
    {
        return array_merge(
            parent::provideBeforeAfterIgnoreCase(),
            [
                ['jàdéjà', 'JÀ', 'déjàdéjà', false],
                ['dé', 'jÀ', 'déjàdéjà', true],
                ['éjàdéjà', 'é', 'déjàdéjà', false],
                ['d', 'é', 'déjàdéjà', true],
                ['', 'Ç', 'déjàdéjà', false],
                ['', 'Ç', 'déjàdéjà', true],
            ]
        );
    }

    public static function provideBeforeAfterLast(): array
    {
        return array_merge(
            parent::provideBeforeAfterLast(),
            [
                ['', 'Ç', 'déjàdéjà', false],
                ['', 'Ç', 'déjàdéjà', true],
                ['éjà', 'é', 'déjàdéjà', false],
                ['déjàd', 'é', 'déjàdéjà', true],
            ]
        );
    }

    public static function provideBeforeAfterLastIgnoreCase(): array
    {
        return array_merge(
            parent::provideBeforeAfterLastIgnoreCase(),
            [
                ['', 'Ç', 'déjàdéjà', false],
                ['éjà', 'é', 'déjàdéjà', false],
                ['éjà', 'É', 'déjàdéjà', false],
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
        $this->assertEquals('Symfony', static::createFromString('Symfony')->replace("\xE9", 'p'));
    }

    public function testReplaceWithInvalidUtf8PatternReplacement()
    {
        $this->expectException(InvalidArgumentException::class);

        static::createFromString('Symfony')->replace('f', "\xE9");
    }
}
