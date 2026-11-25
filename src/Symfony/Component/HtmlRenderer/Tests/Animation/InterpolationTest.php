<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HtmlRenderer\Tests\Animation;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlRenderer\Animation\Interpolation;

class InterpolationTest extends TestCase
{
    public function testNumericInterpolationPreservesOvershootingEasing()
    {
        self::assertSame('12.5px', Interpolation::between('0px', '10px', 1.25));
        self::assertSame('-2.5px', Interpolation::between('0px', '10px', -0.25));
    }

    public function testFunctionsAndMixedUnitsUseSharedComponentValues()
    {
        self::assertSame('translate(5px, 10px)', Interpolation::between('translate(0px, 0px)', 'translate(10px, 20px)', 0.5));
        self::assertSame('calc(1em * 0.5 + 20px * 0.5)', Interpolation::between('1em', '20px', 0.5));
        self::assertSame('12px', Interpolation::add('2px', '10px'));
    }

    public function testColorsRetainAlphaWithoutChoosingABackdrop()
    {
        self::assertSame('#800080', Interpolation::between('red', 'blue', 0.5));
        self::assertSame('rgba(255, 0, 0, 0.5)', Interpolation::between('transparent', 'red', 0.5));
    }
}
