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
use Symfony\Component\TuiHtml\Css\DeclarationMapper;
use Symfony\Component\TuiHtml\Css\Length;
use Symfony\Component\TuiHtml\Style\CellSize;
use Symfony\Component\TuiHtml\Style\MediaContext;

final class LengthTest extends TestCase
{
    public function testHugeLengthsAreClamped()
    {
        $media = new MediaContext();
        $this->assertSame(Length::MAX_CELLS, Length::toCells('999999999999ch', $media));
        $this->assertSame(-Length::MAX_CELLS, Length::toCells('-999999999ch', $media));
        $this->assertSame(Length::MAX_CELLS, Length::toCells('calc(99999 * 99999)', $media));
    }

    public function testTabSize()
    {
        $media = new MediaContext();
        $this->assertSame(4, Length::tabSize('4', $media));
        $this->assertSame(3, Length::tabSize('2.5', $media));
        $this->assertSame(6, Length::tabSize('6ch', $media));
        $this->assertSame(3, Length::tabSize('24px', $media));
        $this->assertSame(64, Length::tabSize('1000', $media));
        $this->assertNull(Length::tabSize('-2', $media));
        $this->assertNull(Length::tabSize('wide', $media));
    }

    #[DataProvider('provideLengths')]
    public function testToCells(string $value, ?int $expected, bool $vertical = false, ?CellSize $cell = null)
    {
        $this->assertSame($expected, Length::toCells($value, (new MediaContext())->withCellSize($cell), $vertical));
    }

    public static function provideLengths(): iterable
    {
        yield 'unitless' => ['3', 3];
        yield 'zero' => ['0', 0];
        yield 'decimal' => ['1.4', 1];
        yield 'leading dot' => ['.6', 1];
        yield 'negative' => ['-2', -2];
        yield 'ch' => ['4ch', 4];
        yield 'lh' => ['2lh', 2, true];
        yield 'ex' => ['1ex', 1];
        yield 'root units' => ['calc(1rch + 2rex)', 3];
        yield 'rlh' => ['2rlh', 2, true];
        yield 'px horizontal' => ['16px', 2];
        yield 'px vertical' => ['16px', 1, true];
        yield 'px small but visible' => ['1px', 1];
        yield 'px at 2x' => ['16px', 2, false, new CellSize(16, 32)];
        yield 'calc' => ['calc(2ch + 16px)', 4];
        yield 'calc vertical' => ['calc(32px + 1lh)', 3, true];
        yield 'calc precedence' => ['calc(1 + 2 * 3)', 7];
        yield 'calc parentheses' => ['calc((1 + 2) * 3)', 9];
        yield 'calc division' => ['calc(9ch / 2)', 5];
        yield 'calc unary minus' => ['calc(-2ch + 5)', 3];
        yield 'calc nested' => ['calc(1 + calc(2 * 2))', 5];
        yield 'calc case' => ['CALC(1 + 1)', 2];
        yield 'calc rounds once' => ['calc(0.4 + 0.4 + 0.4)', 1];
        yield 'min' => ['min(10ch, 4)', 4];
        yield 'max' => ['max(1ch, calc(16px * 3))', 6];
        yield 'clamp' => ['clamp(2, 10, 6)', 6];
        yield 'clamp low' => ['clamp(2, 1, 6)', 2];
        yield 'clamp needs three' => ['clamp(1, 2)', null];
        yield 'calc percent' => ['calc(100% - 2ch)', null];
        yield 'calc by zero' => ['calc(4 / 0)', null];
        yield 'calc unclosed' => ['min(1, 2', null];
        yield 'calc garbage' => ['calc(1 ? 2)', null];
        yield 'round' => ['round(7.4)', 7];
        yield 'round half up' => ['round(-2.5)', -2];
        yield 'round up to a step' => ['round(up, 7.1, 2)', 8];
        yield 'round down' => ['round(down, -7.1)', -8];
        yield 'round to zero' => ['round(to-zero, -7.9, 2)', -6];
        yield 'round after conversion' => ['round(down, 15px)', 1];
        yield 'round needs a comma after the strategy' => ['round(up 7)', null];
        yield 'mod' => ['mod(-7, 3)', 2];
        yield 'rem' => ['rem(-7, 3)', -1];
        yield 'abs and sign' => ['calc(abs(-4) * sign(-1))', -4];
        yield 'pow' => ['pow(2, 3)', 8];
        yield 'sqrt' => ['sqrt(16)', 4];
        yield 'hypot' => ['hypot(3ch, 4ch)', 5];
        yield 'exp and log' => ['calc(log(exp(3)) + log(8, 2))', 6];
        yield 'sin of degrees' => ['calc(10 * sin(30deg))', 5];
        yield 'cos of turns' => ['calc(4 * cos(0.5turn))', -4];
        yield 'tan of radians' => ['calc(tan(0rad) + 2)', 2];
        yield 'angle outside trigonometry' => ['calc(30deg)', null];
        yield 'constants' => ['calc(pi + e)', 6];
        yield 'exponent' => ['calc(1e1 + 2)', 12];
        yield 'infinity is clamped' => ['calc(infinity * 1ch)', Length::MAX_CELLS];
        yield 'nan is zero' => ['sqrt(-1)', 0];
        yield 'unknown function' => ['foo(1)', null];
        yield 'unknown constant' => ['calc(tau)', null];
        yield 'px vertical at 2x' => ['32px', 2, true, new CellSize(16, 32)];
        yield 'em' => ['1em', 2];
        yield 'rem vertical' => ['2rem', 2, true];
        yield 'uppercase unit' => ['2CH', 2];
        yield 'whitespace' => [' 2 ', 2];
        yield 'percent' => ['50%', null];
        yield 'keyword' => ['auto', null];
        yield 'empty' => ['', null];
    }

    public function testNumbers()
    {
        $this->assertSame(45.0, Length::number('atan2(1, 1)'), 'Angles in degrees, like hues');
        $this->assertSame(90.0, Length::number('asin(1)'));
        $this->assertSame(3.0, Length::number('round(2.5)'));
        $this->assertSame(0.0, Length::number('calc(nan)'));
        $this->assertSame(1000000000.0, Length::number('calc(infinity)'));
        $this->assertNull(Length::number('calc(1px)'));
        $this->assertSame(120.0, Length::angle('calc(30deg + 0.25turn)'), 'A hue: angles and numbers in degrees');
        $this->assertEqualsWithDelta(5.0, Length::number('calc(10 * sin(calc(30deg)))'), 1.0E-9, 'Through the functions nested in a trigonometric one');
    }

    public function testLinearSizes()
    {
        $media = new MediaContext();
        $this->assertSame([100.0, -20.0], Length::linear('calc(100% - 20ch)', $media));
        $this->assertSame([40.0, 0.0], Length::linear('40%', $media));
        $this->assertSame([50.0, 2.0], Length::linear('calc(50% + 2)', $media));
        $this->assertNull(Length::linear('min(50%, 30ch)', $media), 'Not a share plus columns.');
        $this->assertNull(Length::linear('calc(10ch + 2)', $media), 'No percentage: a length.');
        $this->assertNull(Length::toCells('calc(100% - 2ch)', $media), 'A percentage means nothing without a room.');
        $style = (new DeclarationMapper())->map(['width' => 'calc(100% - 20ch)'], $media);
        $this->assertSame([100, -20, 60], [$style->getWidthPercent(), $style->getWidthOffset(), $style->resolveWidthPercent(80)]);
        $this->assertSame(1, $style->resolveWidthPercent(10), 'At least a column.');
    }
}
