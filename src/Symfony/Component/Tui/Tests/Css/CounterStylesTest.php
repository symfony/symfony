<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Css;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Css\CounterStyles;

final class CounterStylesTest extends TestCase
{
    #[DataProvider('providePredefined')]
    public function testPredefinedStyles(string $style, int $value, string $expected)
    {
        $this->assertSame($expected, (new CounterStyles())->format($value, $style));
    }

    public static function providePredefined(): iterable
    {
        yield 'decimal' => ['decimal', 42, '42'];
        yield 'decimal negative' => ['decimal', -7, '-7'];
        yield 'leading zero' => ['decimal-leading-zero', 7, '07'];
        yield 'leading zero negative' => ['decimal-leading-zero', -7, '-7'];
        yield 'lower-alpha' => ['lower-alpha', 28, 'ab'];
        yield 'lower-alpha out of range' => ['lower-alpha', 0, '0'];
        yield 'upper-latin' => ['upper-latin', 3, 'C'];
        yield 'lower-roman' => ['lower-roman', 1994, 'mcmxciv'];
        yield 'upper-roman out of range' => ['upper-roman', 4000, '4000'];
        yield 'lower-greek' => ['lower-greek', 2, 'β'];
        yield 'cjk-decimal' => ['cjk-decimal', 205, '二〇五'];
        yield 'disc' => ['disc', 3, '•'];
        yield 'unknown' => ['nope', 5, '5'];
        yield 'case' => ['UPPER-ROMAN', 4, 'IV'];
        yield 'arabic-indic' => ['arabic-indic', 42, '٤٢'];
        yield 'devanagari' => ['devanagari', 2026, '२०२६'];
        yield 'thai' => ['thai', 7, '๗'];
        yield 'hiragana' => ['hiragana', 49, 'ああ'];
        yield 'katakana-iroha' => ['katakana-iroha', 3, 'ハ'];
        yield 'cjk-heavenly-stem' => ['cjk-heavenly-stem', 10, '癸'];
        yield 'upper-armenian' => ['upper-armenian', 1994, 'ՌՋՂԴ'];
        yield 'lower-armenian' => ['lower-armenian', 36, 'լզ'];
        yield 'armenian out of range' => ['armenian', 10000, '10000'];
        yield 'georgian' => ['georgian', 19999, 'ჵჰშჟთ'];
        yield 'hebrew 15 and 16' => ['hebrew', 116, 'קטז'];
        yield 'hebrew thousands' => ['hebrew', 5785, 'ה׳תשפה'];
        yield 'japanese-informal' => ['japanese-informal', 1005, '千五'];
        yield 'japanese-informal negative' => ['japanese-informal', -21, 'マイナス二十一'];
        yield 'japanese-formal' => ['japanese-formal', 10, '壱拾'];
        yield 'korean-hangul-formal' => ['korean-hangul-formal', 1234, '일천이백삼십사'];
        yield 'korean-hanja-informal' => ['korean-hanja-informal', 1000, '千'];
        yield 'simp-chinese-informal ten' => ['simp-chinese-informal', 12, '十二'];
        yield 'simp-chinese-informal zeros' => ['simp-chinese-informal', 1010, '一千零一十'];
        yield 'simp-chinese-informal zero' => ['simp-chinese-informal', 0, '零'];
        yield 'trad-chinese-formal' => ['trad-chinese-formal', 1001, '壹仟零壹'];
        yield 'chinese negative' => ['trad-chinese-informal', -12, '負十二'];
        yield 'chinese out of range' => ['simp-chinese-formal', 10000, '一〇〇〇〇'];
        yield 'ethiopic-numeric one' => ['ethiopic-numeric', 1, '፩'];
        yield 'ethiopic-numeric hundred' => ['ethiopic-numeric', 100, '፻'];
        yield 'ethiopic-numeric' => ['ethiopic-numeric', 78010092, '፸፰፻፩፼፺፪'];
        yield 'ethiopic-numeric ten thousand' => ['ethiopic-numeric', 12345, '፼፳፫፻፵፭'];
        yield 'ethiopic-numeric out of range' => ['ethiopic-numeric', 0, '0'];
    }

    public function testMarkersHaveThePrefixAndSuffix()
    {
        $styles = new CounterStyles();
        $this->assertSame('3. ', $styles->marker(3, 'decimal'));
        $this->assertSame('◦ ', $styles->marker(3, 'circle'));
        $this->assertSame('、', mb_substr($styles->marker(1, 'cjk-decimal'), -1));
    }

    #[DataProvider('provideSystems')]
    public function testSystems(array $descriptors, int $value, string $expected)
    {
        $styles = new CounterStyles();
        $styles->add(['custom' => CounterStyles::parse('custom', $descriptors) ?? throw new \LogicException('Invalid style.')]);

        $this->assertSame($expected, $styles->marker($value, 'custom'));
    }

    public static function provideSystems(): iterable
    {
        yield 'cyclic' => [['system' => 'cyclic', 'symbols' => '"a" "b"', 'suffix' => '""'], 3, 'a'];
        yield 'cyclic negative' => [['system' => 'cyclic', 'symbols' => 'a b c', 'suffix' => '""'], -1, 'b'];
        yield 'fixed' => [['system' => 'fixed 5', 'symbols' => 'x y', 'suffix' => '""'], 6, 'y'];
        yield 'fixed out of its symbols' => [['system' => 'fixed', 'symbols' => 'x y', 'suffix' => '""'], 3, '3'];
        yield 'symbolic' => [['system' => 'symbolic', 'symbols' => '"*" "†"', 'suffix' => '""'], 5, '***'];
        yield 'alphabetic' => [['system' => 'alphabetic', 'symbols' => '"0" "1"', 'suffix' => '""'], 3, '00'];
        yield 'numeric' => [['system' => 'numeric', 'symbols' => '"0" "1"', 'suffix' => '""'], 6, '110'];
        yield 'numeric negative' => [['system' => 'numeric', 'symbols' => '"0" "1"', 'negative' => '"(" ")"', 'suffix' => '""'], -2, '(10)'];
        yield 'additive' => [['system' => 'additive', 'additive-symbols' => '5 V, 1 I', 'suffix' => '""'], 7, 'VII'];
        yield 'additive zero' => [['system' => 'additive', 'additive-symbols' => '1 "|", 0 "∅"', 'suffix' => '""'], 0, '∅'];
        yield 'additive not representable' => [['system' => 'additive', 'additive-symbols' => '2 "‖"', 'suffix' => '""'], 3, '3'];
        yield 'prefix and suffix' => [['system' => 'cyclic', 'symbols' => '"-"', 'prefix' => '"["', 'suffix' => '"] "'], 1, '[-] '];
        yield 'default suffix' => [['system' => 'cyclic', 'symbols' => '"-"'], 1, '-. '];
        yield 'pad' => [['system' => 'extends decimal', 'pad' => '3 "0"', 'suffix' => '""'], 7, '007'];
        yield 'range' => [['system' => 'extends upper-roman', 'range' => '1 3, 10 infinite', 'suffix' => '""'], 4, '4'];
        yield 'fallback' => [['system' => 'extends upper-roman', 'range' => '1 3', 'fallback' => 'lower-alpha', 'suffix' => '""'], 4, 'd'];
        yield 'escapes' => [['system' => 'cyclic', 'symbols' => '"\2605"', 'suffix' => '""'], 1, '★'];
    }

    public function testExtendsKeepsTheAlgorithmOfItsBase()
    {
        $styles = new CounterStyles();
        $styles->add(['base' => CounterStyles::parse('base', ['system' => 'alphabetic', 'symbols' => 'x y', 'suffix' => '":"'])]);
        $styles->add(['child' => CounterStyles::parse('child', ['system' => 'extends base', 'prefix' => '"<"'])]);

        $this->assertSame('<xy:', $styles->marker(4, 'child'));
    }

    public function testExtendingInALoopIsDecimal()
    {
        $styles = new CounterStyles();
        $styles->add([
            'a' => CounterStyles::parse('a', ['system' => 'extends b']),
            'b' => CounterStyles::parse('b', ['system' => 'extends a']),
        ]);

        $this->assertSame('12', $styles->format(12, 'a'));
    }

    public function testALaterStyleReplacesAnEarlierOne()
    {
        $styles = new CounterStyles();
        $styles->add(['s' => CounterStyles::parse('s', ['system' => 'cyclic', 'symbols' => 'a'])]);
        $this->assertSame('a', $styles->format(1, 's'));
        $styles->add(['s' => CounterStyles::parse('s', ['system' => 'cyclic', 'symbols' => 'b'])]);
        $this->assertSame('b', $styles->format(1, 's'));
    }

    public function testReservedStylesAreNotRedefined()
    {
        $this->assertNull(CounterStyles::parse('decimal', ['system' => 'cyclic', 'symbols' => 'x']));
        $this->assertNull(CounterStyles::parse('none', ['system' => 'cyclic', 'symbols' => 'x']));
    }

    #[DataProvider('provideInvalid')]
    public function testInvalidStyles(array $descriptors)
    {
        $this->assertNull(CounterStyles::parse('custom', $descriptors));
    }

    public static function provideInvalid(): iterable
    {
        yield 'unknown system' => [['system' => 'magic', 'symbols' => 'a']];
        yield 'no symbols' => [['system' => 'cyclic']];
        yield 'one alphabetic symbol' => [['system' => 'alphabetic', 'symbols' => 'a']];
        yield 'additive without additive-symbols' => [['system' => 'additive', 'symbols' => 'a']];
        yield 'weights going up' => [['system' => 'additive', 'additive-symbols' => '1 I, 5 V']];
        yield 'extends with symbols' => [['system' => 'extends decimal', 'symbols' => 'a']];
        yield 'image symbol' => [['system' => 'cyclic', 'symbols' => 'url(a.png)']];
        yield 'two prefixes' => [['system' => 'cyclic', 'symbols' => 'a', 'prefix' => '"a" "b"']];
        yield 'reversed range' => [['system' => 'cyclic', 'symbols' => 'a', 'range' => '5 1']];
        yield 'bad pad' => [['system' => 'cyclic', 'symbols' => 'a', 'pad' => '"0"']];
    }
}
