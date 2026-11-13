<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Tui\Tests\Style;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Style\FloatShape;
use Symfony\Component\Tui\Style\RasterImage;

final class FloatShapeTest extends TestCase
{
    public function testACircleIsRoundInCellsTwiceAsTallAsWide()
    {
        // 12 columns by 6 rows of 2: a circle of 6 around the middle
        $this->assertSame([11, 12, 12, 12, 12, 11], (new FloatShape('circle'))->profile(12, 6, 'left'));
        $this->assertSame([11, 12, 12, 12, 12, 11], (new FloatShape('circle'))->profile(12, 6, 'right'), 'From the right edge alike.');
        $this->assertSame([0, 3, 3, 3, 3, 0], (new FloatShape('circle', values: [3.0], at: [0.0, 0.5]))->profile(10, 6, 'left'), 'Its radius in columns: 3 columns, a row and a half.');
        $this->assertSame([3, 3, 3, 3, 3, 3], (new FloatShape('circle', values: [3.0], at: [0.0, 0.5], rowRatio: 1.0))->profile(10, 6, 'left'), 'Rows as tall as wide: 3 rows each way.');
    }

    public function testAnEllipseAndItsPosition()
    {
        $this->assertSame([7, 8, 8, 8, 8, 8, 8, 7], (new FloatShape('ellipse', values: ['50%', '50%']))->profile(8, 8, 'left'));
        $this->assertSame([0, 0, 4, 4], (new FloatShape('ellipse', values: [4.0, 4.0], at: [0.0, 1.0]))->profile(8, 4, 'left'), 'At the bottom left: the bottom rows.');
        $this->assertSame([0, 0, 0, 0], (new FloatShape('ellipse', values: ['closest-side', 'closest-side'], at: [0.0, 0.5]))->profile(8, 4, 'left'), 'No room from the left edge: no shape.');
    }

    public function testAPolygon()
    {
        $triangle = new FloatShape('polygon', values: ['100%', '0%', '100%', '100%', '0%', '100%']);

        $this->assertSame([2, 4, 6], $triangle->profile(6, 3, 'right'));
        $this->assertSame([6, 6, 6], $triangle->profile(6, 3, 'left'), 'From the left edge to its right side.');
        $this->assertSame([4, 6, 6], $triangle->profile(6, 3, 'right', grow: 1.0), 'The shape-margin: a column further and the slope reached earlier, within the box.');
    }

    public function testAnImageItsPixelsMoreOpaqueThanTheThreshold()
    {
        // 32 by 32 pixels, 4 columns and 2 rows: opaque on the left half, half transparent on the right
        $image = imagecreatetruecolor(32, 32);
        imagesavealpha($image, true);
        imagealphablending($image, false);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127) ?: 0);
        imagefilledrectangle($image, 0, 0, 15, 31, imagecolorallocatealpha($image, 255, 0, 0, 0) ?: 0);
        imagefilledrectangle($image, 16, 0, 31, 15, imagecolorallocatealpha($image, 255, 0, 0, 64) ?: 0);
        ob_start();
        imagepng($image);
        $shape = new FloatShape('image', image: new RasterImage((string) ob_get_clean()));

        $this->assertSame([4, 2, 0], $shape->profile(8, 3, 'left'), 'Any pixel not transparent, the half transparent ones too; nothing past the image.');
        $this->assertSame([2, 2, 0], $shape->profile(8, 3, 'left', threshold: 0.6), 'Past 0.6: the opaque ones.');
        $this->assertSame([8, 8, 0], $shape->profile(8, 3, 'right'), 'From the right edge: the image at the left of the box.');
        $this->assertSame([3, 3, 3], $shape->profile(8, 3, 'left', threshold: 0.6, grow: 1.0), 'The shape-margin: a column further, and a row.');
        $this->assertSame([0, 4, 2], (new FloatShape('image', 'border-box', image: $shape->image))->profile(8, 3, 'left', [1, 0, 0, 0]), 'From the top of its border box, under its margin.');
    }

    public function testNumbersTooLargeTakeTheBox()
    {
        $this->assertSame([6, 6, 6], (new FloatShape('polygon', values: ['0%', '0%', '1.0E+400%', '0%', '0%', '100%']))->profile(6, 3, 'left'));
    }

    public function testAnInsetWithRoundCornersAndTheBoxes()
    {
        $this->assertSame([7, 8, 8, 8, 8, 8, 8, 8], (new FloatShape('inset', values: [0.0, 0.0, 0.0, 0.0], radii: [0.0, 0.0, 4.0, 8.0, 0.0, 0.0, 0.0, 0.0]))->profile(8, 8, 'left'), 'Its top right corner round.');
        $this->assertSame([7, 8, 8, 8, 8, 8, 8, 7], (new FloatShape('inset', values: [0.0, 0.0, 0.0, 0.0], radii: [8.0, 16.0, 8.0, 16.0, 8.0, 16.0, 8.0, 16.0]))->profile(8, 8, 'left'), 'Radii too large made smaller alike: an ellipse.');
        $this->assertSame([0, 6, 6, 0], (new FloatShape('inset', values: [2.0, 2.0, 2.0, 0.0]))->profile(8, 4, 'left'), 'Its insets: 2 columns and a row.');
        $this->assertSame([8, 8, 8, 8], (new FloatShape('box', 'margin-box'))->profile(8, 4, 'left'));
        $this->assertSame([0, 6, 6, 0], (new FloatShape('box', 'border-box'))->profile(8, 4, 'left', [1, 2, 1, 0]), 'Its margins left out.');
        $this->assertSame([0, 0, 5, 0, 0], (new FloatShape('box', 'content-box'))->profile(8, 5, 'left', [1, 1, 1, 1], [0, 1, 0, 0], [1, 1, 1, 0]), 'Margin, border and padding left out.');
        $this->assertSame([0, 0, 0, 0], (new FloatShape('inset', values: ['50%', '50%', '50%', '50%']))->profile(8, 4, 'left'), 'Nothing left.');
    }
}
