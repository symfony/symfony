<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\Constraints;

use Symfony\Component\Validator\Constraints\CssColor;
use Symfony\Component\Validator\Constraints\CssColorValidator;
use Symfony\Component\Validator\Exception\UnexpectedValueException;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

final class CssColorValidatorTest extends ConstraintValidatorTestCase
{
    protected function createValidator(): CssColorValidator
    {
        return new CssColorValidator();
    }

    public function testNullIsValid()
    {
        $this->validator->validate(null, new CssColor(CssColor::HEX_LONG));

        $this->assertNoViolation();
    }

    public function testEmptyStringIsValid()
    {
        $this->validator->validate('', new CssColor(CssColor::HEX_LONG));

        $this->assertNoViolation();
    }

    public function testExpectsStringCompatibleType()
    {
        $this->expectException(UnexpectedValueException::class);
        $this->validator->validate(new \stdClass(), new CssColor(CssColor::HEX_LONG));
    }

    /**
     * @dataProvider getValidAnyColor
     */
    public function testValidAnyColor($cssColor)
    {
        $this->validator->validate($cssColor, new CssColor());
        $this->assertNoViolation();
    }

    public static function getValidAnyColor(): array
    {
        return [
            ['#ABCDEF'],
            ['#ABCDEF00'],
            ['#F4B'],
            ['#F4B1'],
            ['black'],
            ['aliceblue'],
            ['Canvas'],
            ['transparent'],
            ['rgb(255, 255, 255)'],
            ['rgba(255, 255, 255, 0.3)'],
            ['hsl(0, 0%, 20%)'],
            ['hsla(0, 0%, 20%, 0.4)'],
        ];
    }

    /**
     * @dataProvider getValidHexLongColors
     */
    public function testValidHexLongColors($cssColor)
    {
        $this->validator->validate($cssColor, new CssColor(CssColor::HEX_LONG));
        $this->assertNoViolation();
    }

    public static function getValidHexLongColors(): array
    {
        return [['#ABCDEF'], ['#abcdef'], ['#C0FFEE'], ['#c0ffee'], ['#501311']];
    }

    /**
     * @dataProvider getValidHexLongColorsWithAlpha
     */
    public function testValidHexLongColorsWithAlpha($cssColor)
    {
        $this->validator->validate($cssColor, new CssColor(CssColor::HEX_LONG_WITH_ALPHA));
        $this->assertNoViolation();
    }

    public static function getValidHexLongColorsWithAlpha(): array
    {
        return [['#ABCDEF00'], ['#abcdef01'], ['#C0FFEE02'], ['#c0ffee03'], ['#501311FF']];
    }

    /**
     * @dataProvider getValidHexShortColors
     */
    public function testValidHexShortColors($cssColor)
    {
        $this->validator->validate($cssColor, new CssColor(CssColor::HEX_SHORT));
        $this->assertNoViolation();
    }

    public static function getValidHexShortColors(): array
    {
        return [['#F4B'], ['#FAB'], ['#f4b'], ['#fab']];
    }

    /**
     * @dataProvider getValidHexShortColorsWithAlpha
     */
    public function testValidHexShortColorsWithAlpha($cssColor)
    {
        $this->validator->validate($cssColor, new CssColor(CssColor::HEX_SHORT_WITH_ALPHA));
        $this->assertNoViolation();
    }

    public static function getValidHexShortColorsWithAlpha(): array
    {
        return [['#F4B1'], ['#FAB1'], ['#f4b1'], ['#fab1']];
    }

    /**
     * @dataProvider getValidBasicNamedColors
     */
    public function testValidBasicNamedColors($cssColor)
    {
        $this->validator->validate($cssColor, new CssColor(CssColor::BASIC_NAMED_COLORS));
        $this->assertNoViolation();
    }

    public static function getValidBasicNamedColors(): array
    {
        return [
            ['black'], ['silver'], ['gray'], ['white'], ['maroon'], ['red'], ['purple'], ['fuchsia'], ['green'], ['lime'], ['olive'], ['yellow'], ['navy'], ['blue'], ['teal'], ['aqua'],
            ['BLACK'], ['SILVER'], ['GRAY'], ['WHITE'], ['MAROON'], ['RED'], ['PURPLE'], ['FUCHSIA'], ['GREEN'], ['LIME'], ['OLIVE'], ['YELLOW'], ['NAVY'], ['BLUE'], ['TEAL'], ['AQUA'],
        ];
    }

    /**
     * @dataProvider getValidExtendedNamedColors
     */
    public function testValidExtendedNamedColors($cssColor)
    {
        $this->validator->validate($cssColor, new CssColor(CssColor::EXTENDED_NAMED_COLORS));
        $this->assertNoViolation();
    }

    public static function getValidExtendedNamedColors(): array
    {
        return [
            ['aliceblue'], ['antiquewhite'], ['aqua'], ['aquamarine'], ['azure'], ['beige'], ['bisque'], ['black'], ['blanchedalmond'], ['blue'], ['blueviolet'], ['brown'], ['burlywood'], ['cadetblue'], ['chartreuse'], ['chocolate'], ['coral'], ['cornflowerblue'], ['cornsilk'], ['crimson'], ['cyan'], ['darkblue'], ['darkcyan'], ['darkgoldenrod'], ['darkgray'], ['darkgreen'], ['darkgrey'], ['darkkhaki'], ['darkmagenta'], ['darkolivegreen'], ['darkorange'], ['darkorchid'], ['darkred'], ['darksalmon'], ['darkseagreen'], ['darkslateblue'], ['darkslategray'], ['darkslategrey'], ['darkturquoise'], ['darkviolet'], ['deeppink'], ['deepskyblue'], ['dimgray'], ['dimgrey'], ['dodgerblue'], ['firebrick'], ['floralwhite'], ['forestgreen'], ['fuchsia'], ['gainsboro'], ['ghostwhite'], ['gold'], ['goldenrod'], ['gray'], ['green'], ['greenyellow'], ['grey'], ['honeydew'], ['hotpink'], ['indianred'], ['indigo'], ['ivory'], ['khaki'], ['lavender'], ['lavenderblush'], ['lawngreen'], ['lemonchiffon'], ['lightblue'], ['lightcoral'], ['lightcyan'], ['lightgoldenrodyellow'], ['lightgray'], ['lightgreen'], ['lightgrey'], ['lightpink'], ['lightsalmon'], ['lightseagreen'], ['lightskyblue'], ['lightslategray'], ['lightslategrey'], ['lightsteelblue'], ['lightyellow'], ['lime'], ['limegreen'], ['linen'], ['magenta'], ['maroon'], ['mediumaquamarine'], ['mediumblue'], ['mediumorchid'], ['mediumpurple'], ['mediumseagreen'], ['mediumslateblue'], ['mediumspringgreen'], ['mediumturquoise'], ['mediumvioletred'], ['midnightblue'], ['mintcream'], ['mistyrose'], ['moccasin'], ['navajowhite'], ['navy'], ['oldlace'], ['olive'], ['olivedrab'], ['orange'], ['orangered'], ['orchid'], ['palegoldenrod'], ['palegreen'], ['paleturquoise'], ['palevioletred'], ['papayawhip'], ['peachpuff'], ['peru'], ['pink'], ['plum'], ['powderblue'], ['purple'], ['red'], ['rosybrown'], ['royalblue'], ['saddlebrown'], ['salmon'], ['sandybrown'], ['seagreen'], ['seashell'], ['sienna'], ['silver'], ['skyblue'], ['slateblue'], ['slategray'], ['slategrey'], ['snow'], ['springgreen'], ['steelblue'], ['tan'], ['teal'], ['thistle'], ['tomato'], ['turquoise'], ['violet'], ['wheat'], ['white'], ['whitesmoke'], ['yellow'], ['yellowgreen'],
            ['ALICEBLUE'], ['ANTIQUEWHITE'], ['AQUA'], ['AQUAMARINE'], ['AZURE'], ['BEIGE'], ['BISQUE'], ['BLACK'], ['BLANCHEDALMOND'], ['BLUE'], ['BLUEVIOLET'], ['BROWN'], ['BURLYWOOD'], ['CADETBLUE'], ['CHARTREUSE'], ['CHOCOLATE'], ['CORAL'], ['CORNFLOWERBLUE'], ['CORNSILK'], ['CRIMSON'], ['CYAN'], ['DARKBLUE'], ['DARKCYAN'], ['DARKGOLDENROD'], ['DARKGRAY'], ['DARKGREEN'], ['DARKGREY'], ['DARKKHAKI'], ['DARKMAGENTA'], ['DARKOLIVEGREEN'], ['DARKORANGE'], ['DARKORCHID'], ['DARKRED'], ['DARKSALMON'], ['DARKSEAGREEN'], ['DARKSLATEBLUE'], ['DARKSLATEGRAY'], ['DARKSLATEGREY'], ['DARKTURQUOISE'], ['DARKVIOLET'], ['DEEPPINK'], ['DEEPSKYBLUE'], ['DIMGRAY'], ['DIMGREY'], ['DODGERBLUE'], ['FIREBRICK'], ['FLORALWHITE'], ['FORESTGREEN'], ['FUCHSIA'], ['GAINSBORO'], ['GHOSTWHITE'], ['GOLD'], ['GOLDENROD'], ['GRAY'], ['GREEN'], ['GREENYELLOW'], ['GREY'], ['HONEYDEW'], ['HOTPINK'], ['INDIANRED'], ['INDIGO'], ['IVORY'], ['KHAKI'], ['LAVENDER'], ['LAVENDERBLUSH'], ['LAWNGREEN'], ['LEMONCHIFFON'], ['LIGHTBLUE'], ['LIGHTCORAL'], ['LIGHTCYAN'], ['LIGHTGOLDENRODYELLOW'], ['LIGHTGRAY'], ['LIGHTGREEN'], ['LIGHTGREY'], ['LIGHTPINK'], ['LIGHTSALMON'], ['LIGHTSEAGREEN'], ['LIGHTSKYBLUE'], ['LIGHTSLATEGRAY'], ['LIGHTSLATEGREY'], ['LIGHTSTEELBLUE'], ['LIGHTYELLOW'], ['LIME'], ['LIMEGREEN'], ['LINEN'], ['MAGENTA'], ['MAROON'], ['MEDIUMAQUAMARINE'], ['MEDIUMBLUE'], ['MEDIUMORCHID'], ['MEDIUMPURPLE'], ['MEDIUMSEAGREEN'], ['MEDIUMSLATEBLUE'], ['MEDIUMSPRINGGREEN'], ['MEDIUMTURQUOISE'], ['MEDIUMVIOLETRED'], ['MIDNIGHTBLUE'], ['MINTCREAM'], ['MISTYROSE'], ['MOCCASIN'], ['NAVAJOWHITE'], ['NAVY'], ['OLDLACE'], ['OLIVE'], ['OLIVEDRAB'], ['ORANGE'], ['ORANGERED'], ['ORCHID'], ['PALEGOLDENROD'], ['PALEGREEN'], ['PALETURQUOISE'], ['PALEVIOLETRED'], ['PAPAYAWHIP'], ['PEACHPUFF'], ['PERU'], ['PINK'], ['PLUM'], ['POWDERBLUE'], ['PURPLE'], ['RED'], ['ROSYBROWN'], ['ROYALBLUE'], ['SADDLEBROWN'], ['SALMON'], ['SANDYBROWN'], ['SEAGREEN'], ['SEASHELL'], ['SIENNA'], ['SILVER'], ['SKYBLUE'], ['SLATEBLUE'], ['SLATEGRAY'], ['SLATEGREY'], ['SNOW'], ['SPRINGGREEN'], ['STEELBLUE'], ['TAN'], ['TEAL'], ['THISTLE'], ['TOMATO'], ['TURQUOISE'], ['VIOLET'], ['WHEAT'], ['WHITE'], ['WHITESMOKE'], ['YELLOW'], ['YELLOWGREEN'],
        ];
    }

    /**
     * @dataProvider getValidSystemColors
     */
    public function testValidSystemColors($cssColor)
    {
        $this->validator->validate($cssColor, new CssColor(CssColor::SYSTEM_COLORS));
        $this->assertNoViolation();
    }

    public static function getValidSystemColors(): array
    {
        return [
            ['Canvas'], ['CanvasText'], ['LinkText'], ['VisitedText'], ['ActiveText'], ['ButtonFace'], ['ButtonText'], ['ButtonBorder'], ['Field'], ['FieldText'], ['Highlight'], ['HighlightText'], ['SelectedItem'], ['SelectedItemText'], ['Mark'], ['MarkText'], ['GrayText'],
            ['canvas'], ['canvastext'], ['linktext'], ['visitedtext'], ['activetext'], ['buttonface'], ['buttontext'], ['buttonborder'], ['field'], ['fieldtext'], ['highlight'], ['highlighttext'], ['selecteditem'], ['selecteditemtext'], ['mark'], ['marktext'], ['graytext'],
            ['CANVAS'], ['CANVASTEXT'], ['LINKTEXT'], ['VISITEDTEXT'], ['ACTIVETEXT'], ['BUTTONFACE'], ['BUTTONTEXT'], ['BUTTONBORDER'], ['FIELD'], ['FIELDTEXT'], ['HIGHLIGHT'], ['HIGHLIGHTTEXT'], ['SELECTEDITEM'], ['SELECTEDITEMTEXT'], ['MARK'], ['MARKTEXT'], ['GRAYTEXT'],
        ];
    }

    /**
     * @dataProvider getValidKeywords
     */
    public function testValidKeywords($cssColor)
    {
        $this->validator->validate($cssColor, new CssColor(CssColor::KEYWORDS));
        $this->assertNoViolation();
    }

    public static function getValidKeywords(): array
    {
        return [['transparent'], ['currentColor']];
    }

    /**
     * @dataProvider getValidRGB
     */
    public function testValidRGB($cssColor)
    {
        $this->validator->validate($cssColor, new CssColor(CssColor::RGB));
        $this->assertNoViolation();
    }

    public static function getValidRGB(): array
    {
        return [
            ['rgb(0,      255,     243)'],
            ['rgb(255, 255, 255)'], ['rgb(0, 0, 0)'], ['rgb(0, 0, 255)'], ['rgb(255, 0, 0)'], ['rgb(122, 122, 122)'], ['rgb(66, 66, 66)'],
            ['rgb(255,255,255)'], ['rgb(0,0,0)'], ['rgb(0,0,255)'], ['rgb(255,0,0)'], ['rgb(122,122,122)'], ['rgb(66,66,66)'],
        ];
    }

    /**
     * @dataProvider getValidRGBA
     */
    public function testValidRGBA($cssColor)
    {
        $this->validator->validate($cssColor, new CssColor(CssColor::RGBA));
        $this->assertNoViolation();
    }

    public static function getValidRGBA(): array
    {
        return [
            ['rgba(   255,      255,     255,    0.3         )'], ['rgba(255,      255,     255,    0.3)'], ['rgba(255,      255,     255,    .3)'],
            ['rgba(255, 255, 255, 0.3)'], ['rgba(0, 0, 0, 0.3)'], ['rgba(0, 0, 255, 0.3)'], ['rgba(255, 0, 0, 0.3)'], ['rgba(122, 122, 122, 0.3)'], ['rgba(66, 66, 66, 0.3)'],
            ['rgba(255,255,255,0.3)'], ['rgba(0,0,0,0.3)'], ['rgba(0,0,255,0.3)'], ['rgba(255,0,0,0.3)'], ['rgba(122,122,122,0.3)'], ['rgba(66,66,66,0.3)'],
            ['rgba(255,255,255,1)'], ['rgba(0,0,0,0)'], ['rgba(0,0,255,1.0)'], ['rgba(255,0,0,0.3)'], ['rgba(122,122,122,.35)'], ['rgba(66,66,66,0.355)'],
        ];
    }

    /**
     * @dataProvider getValidHSL
     */
    public function testValidHSL($cssColor)
    {
        $this->validator->validate($cssColor, new CssColor(CssColor::HSL));
        $this->assertNoViolation();
    }

    public static function getValidHSL(): array
    {
        return [
            ['hsl(0,    0%,   20%)'], ['hsl(     0,    0%,   20%     )'],
            ['hsl(0, 0%, 20%)'], ['hsl(0, 100%, 50%)'], ['hsl(147, 50%, 47%)'], ['hsl(46, 100%, 0%)'],
            ['hsl(0,0%,20%)'], ['hsl(0,100%,50%)'], ['hsl(147,50%,47%)'], ['hsl(46,100%,0%)'],
        ];
    }

    /**
     * @dataProvider getValidHSLA
     */
    public function testValidHSLA($cssColor)
    {
        $this->validator->validate($cssColor, new CssColor(CssColor::HSLA));
        $this->assertNoViolation();
    }

    public static function getValidHSLA(): array
    {
        return [
            ['hsla(   0,    0%,     20%,   0.4     )'], ['hsla(0,    0%,     20%,   0.4)'], ['hsla(0,    0%,     20%,   .4)'],
            ['hsla(0, 0%, 20%, 0.4)'], ['hsla(0, 100%, 50%, 0.4)'], ['hsla(147, 50%, 47%, 0.4)'], ['hsla(46, 100%, 0%, 0.4)'],
            ['hsla(0,0%,20%,0.4)'], ['hsla(0,100%,50%,0.4)'], ['hsla(147,50%,47%,0.4)'], ['hsla(46,100%,0%,0.4)'],
            ['hsla(0,0%,20%,1)'], ['hsla(0,100%,50%,0)'], ['hsla(147,50%,47%,1.0)'], ['hsla(46,100%,0%,.34)'], ['hsla(46,100%,0%,0.355)'],
        ];
    }

    /**
     * @dataProvider getInvalidHexColors
     */
    public function testInvalidHexColors($cssColor)
    {
        $constraint = new CssColor([CssColor::HEX_LONG, CssColor::HEX_LONG_WITH_ALPHA], 'myMessage');
        $this->validator->validate($cssColor, $constraint);

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$cssColor.'"')
            ->setCode(CssColor::INVALID_FORMAT_ERROR)
            ->assertRaised();
    }

    public static function getInvalidHexColors(): array
    {
        return [['ABCDEF'], ['abcdef'], ['#K0FFEE'], ['#k0ffee'], ['#_501311'], ['ABCDEF00'], ['abcdefcc'], ['#K0FFEE33'], ['#k0ffeecc'], ['#_50131100'], ['#FAℬ'], ['#Ⅎab'], ['#F4️⃣B'], ['#f(4)b'], ['#907;']];
    }

    /**
     * @dataProvider getInvalidShortHexColors
     */
    public function testInvalidShortHexColors($cssColor)
    {
        $this->validator->validate($cssColor, new CssColor([CssColor::HEX_SHORT, CssColor::HEX_SHORT_WITH_ALPHA], 'myMessage'));

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$cssColor.'"')
            ->setCode(CssColor::INVALID_FORMAT_ERROR)
            ->assertRaised();
    }

    public static function getInvalidShortHexColors(): array
    {
        return [['ABC'], ['ABCD'], ['abc'], ['abcd'], ['#K0F'], ['#K0FF'], ['#k0f'], ['#k0ff'], ['#_50'], ['#_501']];
    }

    /**
     * @dataProvider getInvalidNamedColors
     */
    public function testInvalidNamedColors($cssColor)
    {
        $this->validator->validate($cssColor, new CssColor([
            CssColor::BASIC_NAMED_COLORS,
            CssColor::EXTENDED_NAMED_COLORS,
            CssColor::SYSTEM_COLORS,
            CssColor::KEYWORDS,
        ], 'myMessage'));

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$cssColor.'"')
            ->setCode(CssColor::INVALID_FORMAT_ERROR)
            ->assertRaised();
    }

    public static function getInvalidNamedColors(): array
    {
        return [['fabpot'], ['ngrekas'], ['symfony'], ['FABPOT'], ['NGREKAS'], ['SYMFONY']];
    }

    /**
     * @dataProvider getInvalidRGB
     */
    public function testInvalidRGB($cssColor)
    {
        $this->validator->validate($cssColor, new CssColor([
            CssColor::BASIC_NAMED_COLORS,
            CssColor::EXTENDED_NAMED_COLORS,
            CssColor::SYSTEM_COLORS,
            CssColor::KEYWORDS,
        ], 'myMessage'));

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$cssColor.'"')
            ->setCode(CssColor::INVALID_FORMAT_ERROR)
            ->assertRaised();
    }

    public static function getInvalidRGB(): array
    {
        return [['rgb(999,999,999)'], ['rgb(-99,-99,-99)'], ['rgb(a,b,c)'], ['rgb(99 99, 9 99, 99 9)']];
    }

    /**
     * @dataProvider getInvalidRGBA
     */
    public function testInvalidRGBA($cssColor)
    {
        $this->validator->validate($cssColor, new CssColor([
            CssColor::BASIC_NAMED_COLORS,
            CssColor::EXTENDED_NAMED_COLORS,
            CssColor::SYSTEM_COLORS,
            CssColor::KEYWORDS,
        ], 'myMessage'));

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$cssColor.'"')
            ->setCode(CssColor::INVALID_FORMAT_ERROR)
            ->assertRaised();
    }

    public static function getInvalidRGBA(): array
    {
        return [['rgba(999,999,999,999)'], ['rgba(-99,-99,-99,-99)'], ['rgba(a,b,c,d)'], ['rgba(99 99, 9 99, 99 9, . 9)']];
    }

    /**
     * @dataProvider getInvalidHSL
     */
    public function testInvalidHSL($cssColor)
    {
        $this->validator->validate($cssColor, new CssColor([
            CssColor::BASIC_NAMED_COLORS,
            CssColor::EXTENDED_NAMED_COLORS,
            CssColor::SYSTEM_COLORS,
            CssColor::KEYWORDS,
        ], 'myMessage'));

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$cssColor.'"')
            ->setCode(CssColor::INVALID_FORMAT_ERROR)
            ->assertRaised();
    }

    public static function getInvalidHSL(): array
    {
        return [['hsl(1000, 1000%, 20000%)'], ['hsl(-100, -10%, -2%)'], ['hsl(a, b, c)'], ['hsl(a, b%, c%)'], ['hsl( 99 99% , 9 99% , 99 9%)']];
    }

    /**
     * @dataProvider getInvalidHSL
     */
    public function testInvalidHSLA($cssColor)
    {
        $this->validator->validate($cssColor, new CssColor([
            CssColor::BASIC_NAMED_COLORS,
            CssColor::EXTENDED_NAMED_COLORS,
            CssColor::SYSTEM_COLORS,
            CssColor::KEYWORDS,
        ], 'myMessage'));

        $this->buildViolation('myMessage')
            ->setParameter('{{ value }}', '"'.$cssColor.'"')
            ->setCode(CssColor::INVALID_FORMAT_ERROR)
            ->assertRaised();
    }

    public function getInvalidHSLA(): array
    {
        return [['hsla(1000, 1000%, 20000%, 999)'], ['hsla(-100, -10%, -2%, 999)'], ['hsla(a, b, c, d)'], ['hsla(a, b%, c%, d)'], ['hsla( 9 99% , 99 9% , 9 %']];
    }

    public function testUnknownFormatsOnValidateTriggerException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The "formats" parameter value is not valid. It must contain one or more of the following values: "hex_long, hex_long_with_alpha, hex_short, hex_short_with_alpha, basic_named_colors, extended_named_colors, system_colors, keywords, rgb, rgba, hsl, hsla".');
        $constraint = new CssColor('Unknown Format');
        $this->validator->validate('#F4B907', $constraint);
    }
}
