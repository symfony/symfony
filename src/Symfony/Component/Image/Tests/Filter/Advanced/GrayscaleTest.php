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

use Symfony\Component\Image\Filter\Advanced\Grayscale;
use Symfony\Component\Image\Image\Box;
use Symfony\Component\Image\Image\BoxInterface;
use Symfony\Component\Image\Image\Point;
use Symfony\Component\Image\Tests\Filter\FilterTestCase;
use Symfony\Component\Image\Image\Palette\Color\ColorInterface;

class GrayscaleTest extends FilterTestCase
{
    /**
     * @covers \Symfony\Component\Image\Filter\Advanced\Grayscale::apply
     *
     * @dataProvider getDataSet
     *
     * @param BoxInterface   $size
     * @param ColorInterface $color
     * @param ColorInterface $filteredColor
     */
    public function testGrayscaling(BoxInterface $size, ColorInterface $color, ColorInterface $filteredColor)
    {
        $image = $this->getImage();
        $imageWidth = $size->getWidth();
        $imageHeight = $size->getHeight();

        $size = $this->getMockBuilder(BoxInterface::class)->getMock();
        $size->expects($this->once())
             ->method('getWidth')
             ->will($this->returnValue($imageWidth));

        $size->expects($this->once())
             ->method('getHeight')
             ->will($this->returnValue($imageHeight));

        $image->expects($this->any())
            ->method('getSize')
            ->will($this->returnValue($size));

        $image->expects($this->exactly($imageWidth * $imageHeight))
            ->method('getColorAt')
            ->will($this->returnValue($color));

        $color->expects($this->exactly($imageWidth * $imageHeight))
            ->method('grayscale')
            ->will($this->returnValue($filteredColor));

        $draw = $this->getDrawer();
        $draw->expects($this->exactly($imageWidth * $imageHeight))
            ->method('dot')
            ->with($this->isInstanceOf(Point::class), $this->equalTo($filteredColor));

        $image->expects($this->exactly($imageWidth * $imageHeight))
              ->method('draw')
              ->will($this->returnValue($draw));

        $filter = new Grayscale();
        $this->assertSame($image, $filter->apply($image));
    }

    /**
     * Data provider for testShouldCanvasImageAndReturnResult.
     *
     * @return array
     */
    public function getDataSet()
    {
        return array(
            array(new Box(20, 10), $this->getColor(), $this->getColor()),
            array(new Box(10, 15), $this->getColor(), $this->getColor()),
            array(new Box(12, 23), $this->getColor(), $this->getColor()),
        );
    }
}
