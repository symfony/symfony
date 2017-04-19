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

use Symfony\Component\Image\Filter\Advanced\Border;
use Symfony\Component\Image\Image\BoxInterface;
use Symfony\Component\Image\Image\Palette\Color\ColorInterface;
use Symfony\Component\Image\Tests\Filter\FilterTestCase;

class BorderTest extends FilterTestCase
{
    public function testBorderImage()
    {
        $color = $this->getMockBuilder(ColorInterface::class)->getMock();
        $width = 2;
        $height = 4;
        $image = $this->getImage();

        $size = $this->getMockBuilder(BoxInterface::class)->getMock();
        $size->expects($this->once())
             ->method('getWidth')
             ->will($this->returnValue($width));

        $size->expects($this->once())
             ->method('getHeight')
             ->will($this->returnValue($height));

        $draw = $this->getDrawer();
        $draw->expects($this->exactly(4))
             ->method('line')
             ->will($this->returnValue($draw));

        $image->expects($this->once())
              ->method('getSize')
              ->will($this->returnValue($size));

        $image->expects($this->once())
              ->method('draw')
              ->will($this->returnValue($draw));

        $filter = new Border($color, $width, $height);

        $this->assertSame($image, $filter->apply($image));
    }
}
