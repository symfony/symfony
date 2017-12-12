<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Utf8\Tests;

use Symfony\Component\Utf8\Bytes;
use Symfony\Component\Utf8\CodePoints;
use Symfony\Component\Utf8\Graphemes;

/**
 * @requires PHP 7
 */
class GraphemesTest extends AbstractUtf8TestCase
{
    protected function setUp()
    {
        if (!function_exists('grapheme_strlen')) {
            $this->markTestSkipped('Intl extension with Grapheme support is required to run this test.');
        }
    }

    protected static function createFromString(string $string)
    {
        return Graphemes::fromString($string);
    }

    /**
     * @dataProvider provideCreateFromCodePointData
     */
    public function testCreateFromCodePoint(string $expected, array $codePoint)
    {
        $this->assertEquals(Graphemes::fromString($expected), call_user_func_array(array(Graphemes::class, 'fromCodePoint'), $codePoint));
    }

    public static function provideWidthData()
    {
        return array_merge(
            parent::provideWidthData(),
            array(
                array(5, 'अनुच्छेद'),
            )
        );
    }

    public function testToBytes()
    {
        $this->assertInstanceOf(Bytes::class, Graphemes::fromString('Symfony')->toBytes());
    }

    public function testToCodePoints()
    {
        $this->assertInstanceOf(CodePoints::class, Graphemes::fromString('Symfony')->toCodePoints());
    }

    public function testToGraphemes()
    {
        $graphemes = Graphemes::fromString('Symfony');

        $this->assertSame($graphemes, $graphemes->toGraphemes());
    }

    public static function provideLength()
    {
        return array_merge(
            parent::provideLength(),
            array(
                // 5 letters + 3 combining marks
                array(5, 'अनुच्छेद'),
            )
        );
    }

    public static function provideStringToExplode()
    {
        return array_merge(
            parent::provideStringToExplode(),
            array(
                array(
                    'अ.नु.च्.छे.द',
                    '.',
                    array(
                        Graphemes::fromString('अ'),
                        Graphemes::fromString('नु'),
                        Graphemes::fromString('च्'),
                        Graphemes::fromString('छे'),
                        Graphemes::fromString('द'),
                    ),
                    null,
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
                    'अनुच्छेद',
                    array(
                        Graphemes::fromString('अ'),
                        Graphemes::fromString('नु'),
                        Graphemes::fromString('च्'),
                        Graphemes::fromString('छे'),
                        Graphemes::fromString('द'),
                    ),
                    1,
                ),
            )
        );
    }

    /**
     * @dataProvider provideGetIteratorData
     */
    public function testGetIteratorWithoutIntlExtensionEnabled(string $string, array $chunks, int $limit)
    {
        $r = new \ReflectionProperty(Graphemes::class, 'hasIntl');
        $r->setAccessible(true);
        $r->setValue(false);

        $this->assertEquals(
            $chunks,
            iterator_to_array(static::createFromString($string)->getIterator($limit))
        );

        $r->setValue(null);
    }

    public static function provideLowercaseData()
    {
        return array_merge(
            parent::provideLowercaseData(),
            array(
                // Hindi
                array('अनुच्छेद', 'अनुच्छेद'),
            )
        );
    }

    public static function provideUppercaseData()
    {
        return array_merge(
            parent::provideUppercaseData(),
            array(
                // Hindi
                array('अनुच्छेद', 'अनुच्छेद'),
            )
        );
    }

    public static function provideSuffixToAppend()
    {
        return array_merge(
            parent::provideSuffixToAppend(),
            array(
                array(
                    'तद्भव देशज',
                    array('तद्भव', ' ', 'देशज'),
                ),
                array(
                    'तद्भव देशज विदेशी',
                    array('तद्भव', ' देशज', ' विदेशी'),
                ),
            )
        );
    }

    public static function providePrefixToPrepend()
    {
        return array_merge(
            parent::providePrefixToPrepend(),
            array(
                array(
                    'देशज तद्भव',
                    array('तद्भव', 'देशज '),
                ),
                array(
                    'विदेशी देशज तद्भव',
                    array('तद्भव', 'देशज ', 'विदेशी '),
                ),
            )
        );
    }

    public static function provideReverseData()
    {
        return array_merge(
            parent::provideReverseData(),
            array(
                array('द.छे.च्.नु.अ', 'अ.नु.च्.छे.द'),
            )
        );
    }

    public static function provideSubstringOfData()
    {
        return array_merge(
            parent::provideSubstringOfData(),
            array(
                array(Graphemes::fromString('द foo अनुच्छेद'), 'द', 'अनुच्छेद foo अनुच्छेद', false),
                array(Graphemes::fromString('अनुच्छे'), 'द', 'अनुच्छेद foo अनुच्छेद', true),
            )
        );
    }

    public static function provideSubstringOfIgnoreCaseData()
    {
        return array_merge(
            parent::provideSubstringOfIgnoreCaseData(),
            array(
                array(null, 'छेछे', 'दछेच्नुअ', false),
                array(null, 'छेछे', 'दछेच्नुअ', true),
                array(Graphemes::fromString('छेच्नुअ'), 'छे', 'दछेच्नुअ', false),
                array(Graphemes::fromString('द'), 'छे', 'दछेच्नुअ', true),
            )
        );
    }

    public static function provideLastSubstringOfData()
    {
        return array_merge(
            parent::provideLastSubstringOfData(),
            array(
                array(null, 'छेछे', 'दछेच्नुअ-दछेच्नु-अदछेच्नु', false),
                array(null, 'छेछे', 'दछेच्नुअ-दछेच्नु-अदछेच्नु', true),
                array(Graphemes::fromString('-दछेच्नु'), '-द', 'दछेच्नुअ-दछेच्नु-अद-दछेच्नु', false),
                array(Graphemes::fromString('दछेच्नुअ-दछेच्नु-अद'), '-द', 'दछेच्नुअ-दछेच्नु-अद-दछेच्नु', true),
            )
        );
    }

    public static function provideLastSubstringOfIgnoreCaseData()
    {
        return array_merge(
            parent::provideLastSubstringOfIgnoreCaseData(),
            array(
                array(null, 'छेछे', 'दछेच्नुअ-दछेच्नु-अदछेच्नु', false),
                array(null, 'छेछे', 'दछेच्नुअ-दछेच्नु-अदछेच्नु', true),
                array(Graphemes::fromString('-दछेच्नु'), '-द', 'दछेच्नुअ-दछेच्नु-अद-दछेच्नु', false),
                array(Graphemes::fromString('दछेच्नुअ-दछेच्नु-अद'), '-द', 'दछेच्नुअ-दछेच्नु-अद-दछेच्नु', true),
            )
        );
    }

    public static function provideReplaceData()
    {
        return array_merge(
            parent::provideReplaceData(),
            array(
                array('Das Innenministerium', 1, 'Das Außenministerium', 'Auß', 'Inn'),
                array('दछेच्नुद-दछेच्नु-ददछेच्नु', 2, 'दछेच्नुअ-दछेच्नु-अदछेच्नु', 'अ', 'द'),
            )
        );
    }

    public static function provideReplaceAllData()
    {
        return array_merge(
            parent::provideReplaceAllData(),
            array(
                array('漢च्नुअ漢च्नुअ漢च्नु', 5, 'दछेच्नुअ-दछेच्नु-अदछेच्नु', array('-', 'दछे'), array('', '漢')),
            )
        );
    }

    public static function provideReplaceIgnoreCaseData()
    {
        return array_merge(
            parent::provideReplaceIgnoreCaseData(),
            array(
                array('Das Aussenministerium', 1, 'Das Außenministerium', 'auß', 'Auss'),
                array('दछेच्नुद-दछेच्नु-ददछेच्नु', 2, 'दछेच्नुअ-दछेच्नु-अदछेच्नु', 'अ', 'द'),
            )
        );
    }

    public static function provideReplaceAllIgnoreCaseData()
    {
        return array_merge(
            parent::provideReplaceAllIgnoreCaseData(),
            array(
                array('漢च्नुअ漢च्नुअ漢च्नु', 5, 'दछेच्नुअ-दछेच्नु-अदछेच्नु', array('-', 'दछे'), array('', '漢')),
            )
        );
    }
}
