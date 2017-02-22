<?php

namespace Symfony\Component\Utf8\Tests;

/**
 * @requires PHP 7
 */
abstract class AbstractUtf8TestCase extends AbstractAsciiTestCase
{
    /**
     * @expectedException \Symfony\Component\Utf8\Exception\InvalidArgumentException
     * @expectedExceptionMessage Given string is not a valid UTF-8 encoded string.
     */
    public function testCreateFromStringWithInvalidUtf8Input()
    {
        static::createFromString("\xE9");
    }

    public function provideCreateFromCodePointData()
    {
        return array(
            array('', array()),
            array('*', array(42)),
            array('AZ', array(65, 90)),
            array('€', array(8364)),
            array('€', array(0x20ac)),
            array('Ʃ', array(425)),
            array('Ʃ', array(0x1a9)),
            array('☢☎❄', array(0x2622, 0x260E, 0x2744)),
        );
    }

    public static function provideLength()
    {
        return array(
            array(1, 'a'),
            array(1, 'ß'),
            array(2, 'is'),
            array(3, 'PHP'),
            array(3, '한국어'),
            array(4, 'Java'),
            array(7, 'Symfony'),
            array(10, 'pineapples'),
            array(22, 'Symfony is super cool!'),
        );
    }

    public static function provideIndexOfData()
    {
        return array_merge(
            parent::provideIndexOfData(),
            array(
                array(1, '한국어', '국', 0),
                array(1, '한국어', '국', 1),
                array(null, '한국어', '국', 2),
                array(8, 'der Straße nach Paris', 'ß', 4),
            )
        );
    }

    public static function provideIndexOfIgnoreCaseData()
    {
        return array_merge(
            parent::provideIndexOfIgnoreCaseData(),
            array(
                array(3, 'DÉJÀ', 'À', 0),
                array(3, 'DÉJÀ', 'à', 0),
                array(1, 'DÉJÀ', 'É', 1),
                array(1, 'DÉJÀ', 'é', 1),
                array(1, 'aςσb', 'ΣΣ', 0),
                array(16, 'der Straße nach Paris', 'Paris', 0),
                array(8, 'der Straße nach Paris', 'ß', 4),
            )
        );
    }

    public static function provideLastIndexOfData()
    {
        return array_merge(
            parent::provideLastIndexOfData(),
            array(
                array(null, '한국어', '', 0),
                array(1, '한국어', '국', 0),
                array(5, '한국어어어어국국', '어', 0),
                // see https://bugs.php.net/bug.php?id=74264
                array(15, 'abcdéf12é45abcdéf', 'é', 0),
                array(8, 'abcdéf12é45abcdéf', 'é', -4),
            )
        );
    }

    public static function provideLastIndexOfIgnoreCaseData()
    {
        return array_merge(
            parent::provideLastIndexOfIgnoreCaseData(),
            array(
                array(null, '한국어', '', 0),
                array(3, 'DÉJÀ', 'à', 0),
                array(3, 'DÉJÀ', 'À', 0),
                array(6, 'DÉJÀÀÀÀ', 'à', 0),
                array(6, 'DÉJÀÀÀÀ', 'à', 3),
                array(5, 'DÉJÀÀÀÀ', 'àà', 0),
                array(2, 'DÉJÀÀÀÀ', 'jà', 0),
                array(2, 'DÉJÀÀÀÀ', 'jà', -5),
                array(6, 'DÉJÀÀÀÀ!', 'à', -2),
                // see https://bugs.php.net/bug.php?id=74264
                array(5, 'DÉJÀÀÀÀ', 'à', -2),
                array(15, 'abcdéf12é45abcdéf', 'é', 0),
                array(8, 'abcdéf12é45abcdéf', 'é', -4),
                array(1, 'aςσb', 'ΣΣ', 0),
            )
        );
    }

    public static function provideStringToExplode()
    {
        return array_merge(
            parent::provideStringToExplode(),
            array(
                array(
                    '會|意|文|字|/|会|意|文|字',
                    '|',
                    array(
                        static::createFromString('會'),
                        static::createFromString('意'),
                        static::createFromString('文'),
                        static::createFromString('字'),
                        static::createFromString('/'),
                        static::createFromString('会'),
                        static::createFromString('意'),
                        static::createFromString('文'),
                        static::createFromString('字'),
                    ),
                    null,
                ),
                array(
                    '會|意|文|字|/|会|意|文|字',
                    '|',
                    array(
                        static::createFromString('會'),
                        static::createFromString('意'),
                        static::createFromString('文'),
                        static::createFromString('字'),
                        static::createFromString('/|会|意|文|字'),
                    ),
                    5,
                ),
            )
        );
    }

    public static function provideGetIteratorData()
    {
        return array_merge(
            parent::provideGetIteratorData(),
            array(
                array(
                    'déjà',
                    array(
                        static::createFromString('d'),
                        static::createFromString('é'),
                        static::createFromString('j'),
                        static::createFromString('à'),
                    ),
                    1,
                ),
                array(
                    'déjà',
                    array(
                        static::createFromString('dé'),
                        static::createFromString('jà'),
                    ),
                    2,
                ),
            )
        );
    }

    /**
     * @expectedException \Symfony\Component\Utf8\Exception\InvalidArgumentException
     * @expectedExceptionMessage Maximum chunk length must not exceed 65535.
     */
    public function testGetIteratorRejectsHighChunksLimit()
    {
        static::createFromString('foobar')->getIterator(65536)->valid();
    }

    /**
     * @expectedException \Symfony\Component\Utf8\Exception\InvalidArgumentException
     * @expectedExceptionMessage Given chars list is not a valid UTF-8 encoded string.
     */
    public function testTrimWithInvalidUtf8CharList()
    {
        static::createFromString('Symfony')->trim("\xE9");
    }

    /**
     * @expectedException \Symfony\Component\Utf8\Exception\InvalidArgumentException
     * @expectedExceptionMessage Given chars list is not a valid UTF-8 encoded string.
     */
    public function testTrimLeftWithInvalidUtf8CharList()
    {
        static::createFromString('Symfony')->trimLeft("\xE9");
    }

    /**
     * @expectedException \Symfony\Component\Utf8\Exception\InvalidArgumentException
     * @expectedExceptionMessage Given chars list is not a valid UTF-8 encoded string.
     */
    public function testTrimRightWithInvalidUtf8CharList()
    {
        static::createFromString('Symfony')->trimRight("\xE9");
    }

    public static function provideLowercaseData()
    {
        return array_merge(
            parent::provideLowercaseData(),
            array(
                // French
                array('garçon', 'garçon'),
                array('garçon', 'GARÇON'),
                array("œuvre d'art", "Œuvre d'Art"),

                // Spanish
                array('el niño', 'El Niño'),

                // Romanian
                array('împărat', 'Împărat'),

                // Random symbols
                array('déjà σσς iiıi', 'DÉJÀ Σσς İIıi'),
            )
        );
    }

    public static function provideUppercaseData()
    {
        return array_merge(
            parent::provideUppercaseData(),
            array(
                // French
                array('GARÇON', 'garçon'),
                array('GARÇON', 'GARÇON'),
                array("ŒUVRE D'ART", "Œuvre d'Art"),

                // Spanish
                array('EL NIÑO', 'El Niño'),

                // Romanian
                array('ÎMPĂRAT', 'Împărat'),

                // Random symbols
                array('DÉJÀ ΣΣΣ İIII', 'Déjà Σσς İIıi'),
            )
        );
    }

    public static function provideUpperCaseFirstData()
    {
        return array_merge(
            parent::provideUpperCaseFirstData(),
            array(
                array('Deja', 'deja', false),
                array('Σσς', 'σσς', false),
                array('DEJa', 'dEJa', false),
                array('ΣσΣ', 'σσΣ', false),
                array('Deja Σσς DEJa ΣσΣ', 'deja σσς dEJa σσΣ', true),
            )
        );
    }

    public static function provideSubstrData()
    {
        return array_merge(
            parent::provideSubstrData(),
            array(
                array('jà', 'déjà', 2, null),
                array('jà', 'déjà', 2, null),
                array('jà', 'déjà', -2, null),
                array('jà', 'déjà', -2, 3),
                array('', 'déjà', -1, 0),
                array('', 'déjà', 1, -4),
                array('j', 'déjà', -2, -1),
                array('', 'déjà', -2, -2),
                array('', 'déjà', 5, 0),
                array('', 'déjà', -5, 0),
            )
        );
    }

    public static function provideSuffixToAppend()
    {
        return array_merge(
            parent::provideSuffixToAppend(),
            array(
                array(
                    'Déjà Σσς',
                    array('Déjà', ' ', 'Σσς'),
                ),
                array(
                    'Déjà Σσς İIıi',
                    array('Déjà', ' Σσς', ' İIıi'),
                ),
            )
        );
    }

    /**
     * @expectedException \Symfony\Component\Utf8\Exception\InvalidArgumentException
     * @expectedExceptionMessage Given suffix is not a valid UTF-8 encoded string.
     */
    public function testAppendInvalidUtf8String()
    {
        static::createFromString('Symfony')->append("\xE9");
    }

    public static function providePrefixToPrepend()
    {
        return array_merge(
            parent::providePrefixToPrepend(),
            array(
                array(
                    'Σσς Déjà',
                    array('Déjà', 'Σσς '),
                ),
                array(
                    'İIıi Σσς Déjà',
                    array('Déjà', 'Σσς ', 'İIıi '),
                ),
            )
        );
    }

    /**
     * @expectedException \Symfony\Component\Utf8\Exception\InvalidArgumentException
     * @expectedExceptionMessage Given prefix is not a valid UTF-8 encoded string.
     */
    public function testPrependInvalidUtf8String()
    {
        static::createFromString('Symfony')->prepend("\xE9");
    }

    public static function provideReverseData()
    {
        return array_merge(
            parent::provideReverseData(),
            array(
                array('àjéd', 'déjà'),
                array('àjéD ςσΣ', 'Σσς Déjà'),
            )
        );
    }

    public static function provideSubstringOfData()
    {
        return array_merge(
            parent::provideSubstringOfData(),
            array(
                array(static::createFromString('jàdéjà'), 'jà', 'déjàdéjà', false),
                array(static::createFromString('dé'), 'jà', 'déjàdéjà', true),
            )
        );
    }

    public static function provideSubstringOfIgnoreCaseData()
    {
        return array_merge(
            parent::provideSubstringOfIgnoreCaseData(),
            array(
                array(static::createFromString('jàdéjà'), 'JÀ', 'déjàdéjà', false),
                array(static::createFromString('dé'), 'jÀ', 'déjàdéjà', true),
                array(static::createFromString('éjàdéjà'), 'é', 'déjàdéjà', false),
                array(static::createFromString('d'), 'é', 'déjàdéjà', true),
                array(null, 'Ç', 'déjàdéjà', false),
                array(null, 'Ç', 'déjàdéjà', true),
            )
        );
    }

    public static function provideLastSubstringOfData()
    {
        return array_merge(
            parent::provideLastSubstringOfData(),
            array(
                array(null, 'Ç', 'déjàdéjà', false),
                array(null, 'Ç', 'déjàdéjà', true),
                array(static::createFromString('éjà'), 'é', 'déjàdéjà', false),
                array(static::createFromString('déjàd'), 'é', 'déjàdéjà', true),
            )
        );
    }

    public static function provideLastSubstringOfIgnoreCaseData()
    {
        return array_merge(
            parent::provideLastSubstringOfIgnoreCaseData(),
            array(
                array(null, 'Ç', 'déjàdéjà', 0),
                array(static::createFromString('éjà'), 'é', 'déjàdéjà', 0),
                array(static::createFromString('éjà'), 'É', 'déjàdéjà', 0),
            )
        );
    }

    public static function provideWidthData()
    {
        return array_merge(
            parent::provideWidthData(),
            array(
                array(1, "\x1B[32mZ\x1B[0m\x1B[m"),
                array(3, '☢☎❄'),
                array(3, "\x1B[32m☢☎❄\x1B[0m\x1B[m"),
                array(4, 'déjà'),
            )
        );
    }

    public static function provideToFoldedCaseData()
    {
        return array_merge(
            parent::provideToFoldedCaseData(),
            array(
                array('déjà', 'DéjÀ'),
                array('σσσ', 'Σσς'),
                array('iıi̇i', 'Iıİi'),
            )
        );
    }

    public static function provideReplaceData()
    {
        return array_merge(
            parent::provideReplaceData(),
            array(
                array('ΣσΣ', 1, 'Σσς', 'ς', 'Σ'),
                array('漢字はユニコード', 0, '漢字はユニコード', 'foo', 'bar'),
                array('漢字ーユニコード', 1, '漢字はユニコード', 'は', 'ー'),
                array('This is a jamais-vu situation!', 1, 'This is a déjà-vu situation!', 'déjà', 'jamais'),
            )
        );
    }

    public static function provideReplaceAllData()
    {
        return array_merge(
            parent::provideReplaceAllData(),
            array(
                array('σσσ', 2, 'Σσς', array('Σ', 'ς'), array('σ', 'σ')),
                array('ド字ーユニコード', 2, '漢字はユニコード', array('は', '漢'), array('ー', 'ド')),
            )
        );
    }

    public static function provideReplaceIgnoreCaseData()
    {
        return array_merge(
            parent::provideReplaceIgnoreCaseData(),
            array(
                // σ and ς are lowercase variants for Σ
                array('ΣΣΣ', 3, 'σσσ', 'σ', 'Σ'),
                array('ΣΣΣ', 3, 'σσσ', 'ς', 'Σ'),
                array('Σσ', 1, 'σσσ', 'σσ', 'Σ'),
                array('漢字はユニコード', 0, '漢字はユニコード', 'foo', 'bar'),
                array('漢字ーユニコード', 1, '漢字はユニコード', 'は', 'ー'),
                array('This is a jamais-vu situation!', 1, 'This is a déjà-vu situation!', 'DÉjÀ', 'jamais'),
            )
        );
    }

    public static function provideReplaceAllIgnoreCaseData()
    {
        return array_merge(
            parent::provideReplaceAllIgnoreCaseData(),
            array(
                array('ド字ーユニコード', 2, '漢字はユニコード', array('は', '漢'), array('ー', 'ド')),
            )
        );
    }

    /**
     * @expectedException \Symfony\Component\Utf8\Exception\InvalidArgumentException
     * @expectedExceptionMessageRegExp /Given pattern ".+" is not a valid UTF-8 encoded string\./
     */
    public function testReplaceWithInvalidUtf8Pattern()
    {
        static::createFromString('Symfony')->replace("\xE9", 'p');
    }

    /**
     * @expectedException \Symfony\Component\Utf8\Exception\InvalidArgumentException
     * @expectedExceptionMessageRegExp /Given pattern replacement ".+" is not a valid UTF-8 encoded string\./
     */
    public function testReplaceWithInvalidUtf8PatternReplacement()
    {
        static::createFromString('Symfony')->replace('f', "\xE9");
    }

    /**
     * @expectedException \Symfony\Component\Utf8\Exception\InvalidArgumentException
     * @expectedExceptionMessageRegExp /Given pattern ".+" is not a valid UTF-8 encoded string\./
     */
    public function testReplaceIgnoreCaseWithInvalidUtf8Pattern()
    {
        static::createFromString('Symfony')->replaceIgnoreCase("\xE9", 'p');
    }

    /**
     * @expectedException \Symfony\Component\Utf8\Exception\InvalidArgumentException
     * @expectedExceptionMessageRegExp /Given pattern replacement ".+" is not a valid UTF-8 encoded string\./
     */
    public function testReplaceIgnoreCaseWithInvalidUtf8PatternReplacement()
    {
        static::createFromString('Symfony')->replaceIgnoreCase('f', "\xE9");
    }
}
