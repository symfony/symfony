<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Tests\Css;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Exception\InvalidArgumentException;
use Symfony\Component\TuiHtml\Css\Value;
use Symfony\Component\TuiHtml\Style\Color;
use Symfony\Component\TuiHtml\Style\ColorScheme;
use Symfony\Component\TuiHtml\Style\MediaContext;
use Symfony\Component\TuiHtml\Style\TailwindStylesheet;

final class ValueTest extends TestCase
{
    public function testSplit()
    {
        $this->assertSame(['1px', 'solid', 'rgb(1, 2, 3)'], Value::split(' 1px  solid rgb(1, 2, 3) '));
        $this->assertSame([], Value::split(''));
        $this->assertSame(['"✓ "', 'inside'], Value::split('"✓ " inside'), 'A string is whole.');
        $this->assertSame(["'a \\' b'", 'c'], Value::split("'a \\' b' c"), 'Escaped quotes stay in it.');
    }

    #[DataProvider('provideColors')]
    public function testColor(string $value, string $expected, ColorScheme $scheme = ColorScheme::Dark)
    {
        $color = Value::color($value, new MediaContext(80, 24, $scheme));
        $this->assertInstanceOf(Color::class, $color);
        $this->assertSame($expected, $color->toForegroundCode());
    }

    public static function provideColors(): iterable
    {
        yield 'ansi' => ['red', Color::named('red')->toForegroundCode()];
        yield 'ansi uppercase' => ['RED', Color::named('red')->toForegroundCode()];
        yield 'bright with hyphen' => ['bright-red', Color::named('bright_red')->toForegroundCode()];
        yield 'bright with underscore' => ['bright_red', Color::named('bright_red')->toForegroundCode()];
        yield 'gray' => ['gray', Color::named('gray')->toForegroundCode()];
        yield 'css named color' => ['rebeccapurple', Color::hex('#663399')->toForegroundCode()];
        yield 'css named color case' => ['LightGoldenrodYellow', Color::hex('#fafad2')->toForegroundCode()];
        yield 'css named lime' => ['lime', Color::hex('#00ff00')->toForegroundCode()];
        yield 'ansi wins over css' => ['blue', Color::named('blue')->toForegroundCode()];
        yield 'palette' => ['33', Color::palette(33)->toForegroundCode()];
        yield 'tailwind' => ['emerald-500', TailwindStylesheet::getPaletteColor('emerald-500')->toForegroundCode()];
        yield 'hex' => ['#ff5500', Color::hex('#ff5500')->toForegroundCode()];
        yield 'short hex' => ['#f50', Color::hex('#f50')->toForegroundCode()];
        yield 'rgb commas' => ['rgb(255, 85, 0)', Color::rgb(255, 85, 0)->toForegroundCode()];
        // Translucent: blended over the terminal background, dark by default
        yield 'rgb spaces and alpha' => ['rgb(255 85 0 / 50%)', Color::hex('#000000')->mix(Color::rgb(255, 85, 0), 50)->toForegroundCode()];
        yield 'rgba' => ['rgba(255,85,0,.5)', Color::hex('#000000')->mix(Color::rgb(255, 85, 0), 50)->toForegroundCode()];
        yield 'hex with alpha' => ['#ff5500ff', Color::rgb(255, 85, 0)->toForegroundCode()];
        yield 'rgb percents' => ['rgb(100% 0% 0%)', Color::rgb(255, 0, 0)->toForegroundCode()];
        yield 'rgb clamped' => ['rgb(300 -5 0)', Color::rgb(255, 0, 0)->toForegroundCode()];
        yield 'hsl red' => ['hsl(0 100% 50%)', Color::rgb(255, 0, 0)->toForegroundCode()];
        yield 'hsl green' => ['hsl(120, 100%, 50%)', Color::rgb(0, 255, 0)->toForegroundCode()];
        yield 'hsl blue negative hue' => ['hsl(-120 100% 50%)', Color::rgb(0, 0, 255)->toForegroundCode()];
        yield 'hsl with a math hue of angles' => ['hsl(calc(30deg + 0.25turn) 100% 50%)', Color::rgb(0, 255, 0)->toForegroundCode()];
        yield 'hsl with math channels' => ['hsl(calc(60 * 2) calc(50% + 50%) 50%)', Color::rgb(0, 255, 0)->toForegroundCode()];
        yield 'rgb with math channels' => ['rgb(calc(100% - 10%) 0 max(10, 200))', Color::rgb(230, 0, 200)->toForegroundCode()];
        yield 'hsl turn' => ['hsl(0.5turn 100% 50%)', Color::rgb(0, 255, 255)->toForegroundCode()];
        yield 'hsl rad' => ['hsl(3.14159rad 100% 50%)', Color::rgb(0, 255, 255)->toForegroundCode()];
        yield 'hsl grad' => ['hsl(200grad 100% 50%)', Color::rgb(0, 255, 255)->toForegroundCode()];
        yield 'hsl deg and numbers' => ['hsl(240deg 100 50)', Color::rgb(0, 0, 255)->toForegroundCode()];
        yield 'hsl none hue' => ['hsl(none 100% 50%)', Color::rgb(255, 0, 0)->toForegroundCode()];
        yield 'rgb none channel' => ['rgb(255 none 0)', Color::rgb(255, 0, 0)->toForegroundCode()];
        yield 'hsla alpha slash' => ['hsl(0 100% 50% / .5)', Color::hex('#000000')->mix(Color::rgb(255, 0, 0), 50)->toForegroundCode()];
        yield 'hwb green' => ['hwb(120 0% 0%)', Color::rgb(0, 255, 0)->toForegroundCode()];
        yield 'hwb tinted' => ['hwb(0 40% 20%)', Color::rgb(204, 102, 102)->toForegroundCode()];
        yield 'hwb gray' => ['hwb(90 60% 60%)', Color::rgb(128, 128, 128)->toForegroundCode()];
        yield 'hwb alpha' => ['hwb(120 0% 0% / 25%)', Color::hex('#000000')->mix(Color::rgb(0, 255, 0), 25)->toForegroundCode()];
        yield 'oklch red' => ['oklch(62.8% 0.2577 29.23)', Color::rgb(255, 0, 0)->toForegroundCode()];
        yield 'oklch tailwind red-500' => ['oklch(63.7% 0.237 25.331)', Color::hex('#fb2c36')->toForegroundCode()];
        yield 'oklch tailwind blue-500' => ['oklch(62.3% 0.214 259.815)', Color::hex('#2b7fff')->toForegroundCode()];
        yield 'oklch tailwind green-500 out of gamut' => ['oklch(72.3% 0.219 149.579)', Color::hex('#00c950')->toForegroundCode()];
        yield 'oklab red' => ['oklab(0.628 0.2249 0.1258)', Color::rgb(255, 0, 0)->toForegroundCode()];
        yield 'lab red' => ['lab(54.29 80.8 69.89)', Color::rgb(255, 0, 0)->toForegroundCode()];
        yield 'lab white' => ['lab(100% 0 0)', Color::rgb(255, 255, 255)->toForegroundCode()];
        yield 'lch red' => ['lch(54.29 106.84 40.85deg)', Color::rgb(255, 0, 0)->toForegroundCode()];
        yield 'oklch lighter than white' => ['oklch(100% 0.4 30)', Color::rgb(255, 255, 255)->toForegroundCode()];
        yield 'oklch alpha' => ['oklch(62.8% 0.2577 29.23 / 50%)', Color::hex('#000000')->mix(Color::rgb(255, 0, 0), 50)->toForegroundCode()];
        yield 'color srgb' => ['color(srgb 0.5 0.2 1)', Color::rgb(128, 51, 255)->toForegroundCode()];
        yield 'color srgb percents' => ['color(srgb 100% 0% 0%)', Color::rgb(255, 0, 0)->toForegroundCode()];
        yield 'color xyz' => ['color(xyz 0.4124 0.2126 0.0193)', Color::rgb(255, 0, 0)->toForegroundCode()];
        yield 'color xyz-d50' => ['color(xyz-d50 0.4361 0.2225 0.0139)', Color::rgb(255, 0, 0)->toForegroundCode()];
        yield 'color rec2020 white' => ['color(rec2020 1 1 1)', Color::rgb(255, 255, 255)->toForegroundCode()];
        yield 'color prophoto white' => ['color(prophoto-rgb 1 1 1)', Color::rgb(255, 255, 255)->toForegroundCode()];
        yield 'color a98 white' => ['color(a98-rgb 1 1 1)', Color::rgb(255, 255, 255)->toForegroundCode()];
        yield 'color display-p3 green gamut mapped' => ['color(display-p3 0 1 0)', Color::hex('#00fb29')->toForegroundCode()];
        yield 'relative rgb' => ['rgb(from #ff8800 b g r)', Color::hex('#0088ff')->toForegroundCode()];
        yield 'relative rgb with calc' => ['rgb(from #ff8800 calc(r / 2) g b)', Color::hex('#808800')->toForegroundCode()];
        yield 'relative alpha' => ['rgb(from #ff8800 r g b / calc(alpha / 2))', Color::hex('#000000')->mix(Color::hex('#ff8800'), 50)->toForegroundCode()];
        yield 'relative keeps the origin alpha' => ['rgb(from rgb(255 0 0 / 50%) r g b)', Color::hex('#000000')->mix(Color::hex('#ff0000'), 50)->toForegroundCode()];
        yield 'relative hsl hue' => ['hsl(from #ff0000 calc(h + 120) s l)', Color::hex('#00ff00')->toForegroundCode()];
        yield 'relative oklch lighter' => ['oklch(from #2b7fff calc(l + 0.1) c h)', Color::hex('#60a3ff')->toForegroundCode()];
        yield 'relative oklch gray' => ['oklch(from #2b7fff l 0 h)', Color::hex('#868686')->toForegroundCode()];
        yield 'relative color xyz' => ['color(from #ff0000 xyz x y z)', Color::hex('#ff0000')->toForegroundCode()];
        yield 'relative hwb' => ['hwb(from #ff0000 h 50% b)', Color::hex('#ff8080')->toForegroundCode()];
        yield 'relative nested' => ['rgb(from oklch(from #ff0000 l c h) r g b)', Color::hex('#ff0000')->toForegroundCode()];
        yield 'system canvas dark' => ['Canvas', Color::hex('#000000')->toForegroundCode()];
        yield 'system canvas light' => ['Canvas', Color::hex('#ffffff')->toForegroundCode(), ColorScheme::Light];
        yield 'system canvastext' => ['CanvasText', Color::hex('#ffffff')->toForegroundCode()];
        yield 'system linktext light' => ['LinkText', Color::hex('#0000ee')->toForegroundCode(), ColorScheme::Light];
        yield 'system color mixed' => ['color-mix(in srgb, Canvas, CanvasText)', Color::hex('#808080')->toForegroundCode()];
        yield 'light-dark in dark' => ['light-dark(red, blue)', Color::named('blue')->toForegroundCode()];
        yield 'light-dark in light' => ['light-dark(red, blue)', Color::named('red')->toForegroundCode(), ColorScheme::Light];
        yield 'contrast-color of a dark color' => ['contrast-color(#123456)', Color::hex('#ffffff')->toForegroundCode()];
        yield 'contrast-color of a light color' => ['contrast-color(gold)', Color::hex('#000000')->toForegroundCode()];
        yield 'contrast-color of mid gray is black' => ['contrast-color(#808080)', Color::hex('#000000')->toForegroundCode()];
        yield 'contrast-color of a nested function' => ['contrast-color(light-dark(white, oklch(0.3 0.1 250)))', Color::hex('#ffffff')->toForegroundCode()];
        yield 'contrast-color mixed' => ['color-mix(in srgb, contrast-color(black), #000000)', Color::hex('#808080')->toForegroundCode()];
        yield 'light-dark nested' => ['light-dark(rgb(1, 2, 3), #000)', Color::rgb(1, 2, 3)->toForegroundCode(), ColorScheme::Light];
        yield 'color-mix half' => ['color-mix(in srgb, #000000, #ffffff)', Color::hex('#000000')->mix(Color::hex('#ffffff'), 50)->toForegroundCode()];
        yield 'color-mix first percentage' => ['color-mix(in srgb, #000000 25%, #ffffff)', Color::hex('#000000')->mix(Color::hex('#ffffff'), 75)->toForegroundCode()];
        yield 'color-mix in oklab' => ['color-mix(in oklab, #000000, #ffffff 10%)', Color::hex('#030303')->toForegroundCode()];
        yield 'color-mix in oklab by default' => ['color-mix(#000000, #ffffff)', Color::hex('#636363')->toForegroundCode()];
        yield 'color-mix in srgb-linear' => ['color-mix(in srgb-linear, #000000, #ffffff)', Color::hex('#bcbcbc')->toForegroundCode()];
        yield 'color-mix percentage first' => ['color-mix(in srgb, 25% #000000, #ffffff)', Color::hex('#000000')->mix(Color::hex('#ffffff'), 75)->toForegroundCode()];
        yield 'color-mix in hsl shorter hue' => ['color-mix(in hsl, #ff0000, #0000ff)', Color::hex('#ff00ff')->toForegroundCode()];
        yield 'color-mix in hsl longer hue' => ['color-mix(in hsl longer hue, #ff0000, #0000ff)', Color::hex('#00ff00')->toForegroundCode()];
        yield 'color-mix in hsl increasing hue' => ['color-mix(in hsl increasing hue, #ff0000, #0000ff)', Color::hex('#00ff00')->toForegroundCode()];
        yield 'color-mix in hsl decreasing hue' => ['color-mix(in hsl decreasing hue, #ff0000, #0000ff)', Color::hex('#ff00ff')->toForegroundCode()];
        yield 'color-mix achromatic takes the other hue' => ['color-mix(in oklch, white, #0000ff)', Color::hex('#6896fb')->toForegroundCode()];
        yield 'color-mix with transparent' => ['color-mix(in srgb, #ff0000 30%, transparent)', Color::hex('#000000')->mix(Color::hex('#ff0000'), 30)->toForegroundCode()];
        yield 'color-mix percentages under 100' => ['color-mix(in srgb, #ff0000 20%, #0000ff 30%)', Color::hex('#000000')->mix(Color::rgb(102, 0, 153), 50)->toForegroundCode()];
        yield 'color-mix percentages over 100' => ['color-mix(in srgb, #ff0000 60%, #0000ff 60%)', Color::hex('#800080')->toForegroundCode()];
        yield 'color-mix light-dark' => ['color-mix(in srgb, light-dark(#ff0000, #0000ff), #ffffff)', Color::hex('#8080ff')->toForegroundCode()];
    }

    public function testTransparent()
    {
        $media = new MediaContext();
        $this->assertNull(Value::color('transparent', $media));
        $this->assertNull(Value::color('none', $media));
        $this->assertNull(Value::color('initial', $media));
    }

    #[DataProvider('provideInvalidColors')]
    public function testInvalidColor(string $value)
    {
        $this->assertFalse(Value::color($value, new MediaContext()));
    }

    public static function provideInvalidColors(): iterable
    {
        yield 'unknown name' => ['notacolor'];
        yield 'bad hex' => ['#ff555'];
        yield 'palette out of range' => ['256'];
        yield 'rgb too short' => ['rgb(1 2)'];
        yield 'hsl too short' => ['hsl(1 2)'];
        yield 'rgb bad channel' => ['rgb(a b c)'];
        yield 'hsl bad angle unit' => ['hsl(10px 50% 50%)'];
        yield 'hwb legacy commas' => ['hwb(120, 0%, 0%)'];
        yield 'hwb too short' => ['hwb(120 0%)'];
        yield 'two alphas' => ['rgb(1 2 3 / 1 / 1)'];
        yield 'color unknown space' => ['color(cmyk 0 0 0)'];
        yield 'color no space' => ['color(1 0 0)'];
        yield 'oklch legacy commas' => ['oklch(0.5, 0.1, 30)'];
        yield 'lab hue unit' => ['lab(50 10deg 10)'];
        yield 'light-dark one argument' => ['light-dark(red)'];
        yield 'color-mix one color' => ['color-mix(in srgb, red)'];
        yield 'color-mix invalid color' => ['color-mix(in srgb, red, nope)'];
        yield 'contrast-color invalid color' => ['contrast-color(nope)'];
        yield 'math mixing a percentage and a number' => ['rgb(calc(50% + 2) 0 0)'];
        yield 'color-mix unknown space' => ['color-mix(in nope, red, blue)'];
        yield 'color-mix hue method of a rectangular space' => ['color-mix(in srgb longer hue, red, blue)'];
        yield 'color-mix percentage over 100' => ['color-mix(in srgb, red 120%, blue)'];
        yield 'color-mix percentages of 0' => ['color-mix(in srgb, red 0%, blue 0%)'];
        yield 'relative too short' => ['rgb(from #ff8800 r g)'];
        yield 'relative invalid origin' => ['rgb(from nope r g b)'];
        yield 'relative unknown keyword' => ['rgb(from #ff8800 q g b)'];
        yield 'relative color unknown space' => ['color(from red cmyk r g b)'];
        yield 'unknown tailwind family' => ['nope-500'];
    }

    public function testSubstitute()
    {
        $properties = ['--a' => 'red', '--b' => 'var(--a)', '--pad' => '1 2'];
        $this->assertSame('red', Value::substitute('var(--a)', $properties));
        $this->assertSame('red', Value::substitute('var(--b)', $properties));
        $this->assertSame('1px solid red', Value::substitute('1px solid var(--a)', $properties));
        $this->assertSame('blue', Value::substitute('var(--missing, blue)', $properties));
        $this->assertSame('rgb(1, 2, 3)', Value::substitute('var(--missing, rgb(1, 2, 3))', $properties));
        // An empty fallback, like the var(--tw-ring-inset,) of the rings of Tailwind
        $this->assertSame(' 0 0 0 1px red', Value::substitute('var(--missing,) 0 0 0 1px var(--a)', $properties));
        // The fallback is all after the first comma, as written: a comma itself, or a list
        $this->assertSame('a , b', Value::substitute('a var(--missing,,) b', $properties));
        $this->assertSame('x, "y,z"', Value::substitute('var(--missing, x, "y,z")', $properties));
        $this->assertSame('red', Value::substitute('var(--missing, var(--a))', $properties));
        $this->assertSame('1 2 red', Value::substitute('var(--pad) var(--a)', $properties));
        $this->assertSame('no vars', Value::substitute('no vars', $properties));
    }

    public function testSubstituteKeepsTokensApart()
    {
        $properties = ['--width' => '1px', '--style' => 'solid', '--y' => '.5rem', '--x' => '1rem', '--n' => '2', '--rgb' => '255, 0, 0'];
        // Minified, like the var(--bs-border-width)var(--bs-border-style) of a Bootstrap build
        $this->assertSame('1px solid', Value::substitute('var(--width)var(--style)', $properties));
        $this->assertSame('.5rem 1rem', Value::substitute('var(--y)var(--x)', $properties));
        $this->assertSame('2 px', Value::substitute('var(--n)px', $properties));
        // Against punctuation, nothing is added
        $this->assertSame('calc(2*1px)', Value::substitute('calc(var(--n)*1px)', $properties));
        $this->assertSame('rgb(255, 0, 0)', Value::substitute('rgb(var(--rgb))', $properties));
    }

    public function testSubstituteUndefined()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"--missing"');
        Value::substitute('var(--missing)', []);
    }

    public function testSubstituteCycle()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cycle');
        Value::substitute('var(--a)', ['--a' => 'var(--b)', '--b' => 'var(--a)']);
    }

    public function testSubstituteUnclosed()
    {
        $this->expectException(InvalidArgumentException::class);
        Value::substitute('var(--a', ['--a' => 'red']);
    }

    public function testSubstituteEnvironment()
    {
        $this->assertSame('calc(0px + 2ch)', Value::substitute('calc(env(safe-area-inset-left) + 2ch)', []));
        $this->assertSame('0px', Value::substitute('env(KEYBOARD-INSET-HEIGHT, 3)', []), 'A known variable, not its fallback.');
        $this->assertSame('4ch', Value::substitute('env(titlebar-area-x, 4ch)', []));
        $this->assertSame('red', Value::substitute('env(titlebar-area-x, var(--a))', ['--a' => 'red']), 'A fallback can use var().');
        $this->assertSame('0px', Value::substitute('var(--inset)', ['--inset' => 'env(safe-area-inset-top)']), 'A custom property can use env().');
        $this->assertSame('"env(x)"', Value::substitute('"env(x)"', []), 'Not in a string.');
    }

    public function testSubstituteUnknownEnvironment()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"titlebar-area-x"');
        Value::substitute('env(titlebar-area-x)', []);
    }
}
