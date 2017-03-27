<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Image\Tests\Filter\Advanced;

use Symfony\Component\Image\Image\Box;
use Symfony\Component\Image\Image\BoxInterface;
use Symfony\Component\Image\Filter\Advanced\Canvas;
use Symfony\Component\Image\Image\Palette\Color\ColorInterface;
use Symfony\Component\Image\Image\Point;
use Symfony\Component\Image\Image\PointInterface;
use Symfony\Component\Image\Tests\Filter\FilterTestCase;

class CanvasTest extends FilterTestCase
{
    /**
     * @covers \Symfony\Component\Image\Filter\Advanced\Canvas::apply
     *
     * @dataProvider getDataSet
     *
     * @param BoxInterface   $size
     * @param PointInterface $placement
     * @param ColorInterface $background
     */
    public function testShouldCanvasImageAndReturnResult(BoxInterface $size, PointInterface $placement = null, ColorInterface $background = null)
    {
        $placement = $placement ?: new Point(0, 0);
        $image = $this->getImage();

        $canvas = $this->getImage();
        $canvas->expects($this->once())->method('paste')->with($image, $placement);

        $loader = $this->getLoader();
        $loader->expects($this->once())->method('create')->with($size, $background)->will($this->returnValue($canvas));

        $command = new Canvas($loader, $size, $placement, $background);

        $this->assertSame($canvas, $command->apply($image));
    }

    /**
     * Data provider for testShouldCanvasImageAndReturnResult
     *
     * @return array
     */
    public function getDataSet()
    {
        return array(
            array(new Box(50, 15), new Point(10, 10), $this->getColor()),
            array(new Box(300, 25), new Point(15, 15)),
            array(new Box(123, 23)),
        );
    }
}
