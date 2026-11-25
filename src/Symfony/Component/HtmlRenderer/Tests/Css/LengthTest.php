<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Css;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Css\Length;

class LengthTest extends TestCase
{
    public function testMathUsesExplicitMeasurementWithoutRounding()
    {
        $resolve = static function (string $token): ?float {
            if (!preg_match('/^([\d.]+)(px|%|em)?$/', $token, $m)) {
                return null;
            }

            return (float) $m[1] * match ($m[2] ?? '') {
                '%' => 2.005,
                'em' => 12.5,
                default => 1,
            };
        };
        self::assertEqualsWithDelta(87.75, Length::evaluate('calc(50% - 1em)', $resolve), 0.00001);
        self::assertSame(12.5, Length::evaluate('clamp(1px, 1em, 30px)', $resolve));
        self::assertEqualsWithDelta(1.0, Length::evaluate('sin(90deg)', $resolve), 0.00001);
        self::assertSame(6.0, Length::evaluate('round(up, 5.2px, 2px)', $resolve));
    }

    public function testInvalidAndDeepExpressionsAreRejected()
    {
        $resolve = static fn (string $token): ?float => is_numeric($token) ? (float) $token : null;
        self::assertNull(Length::evaluate('calc(1 / 0)', $resolve));
        self::assertSame(1.0, Length::evaluate(str_repeat('calc(', 64).'1'.str_repeat(')', 64), $resolve));
        self::assertNull(Length::evaluate(str_repeat('calc(', 65).'1'.str_repeat(')', 65), $resolve));
        self::assertNull(Length::evaluate('calc(1 + unsupported)', $resolve));
    }
}
