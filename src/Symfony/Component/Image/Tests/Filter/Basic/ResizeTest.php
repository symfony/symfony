<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Image\Tests\Filter\Basic;

use Symfony\Component\Image\Filter\Basic\Resize;
use Symfony\Component\Image\Image\Box;
use Symfony\Component\Image\Image\BoxInterface;
use Symfony\Component\Image\Tests\Filter\FilterTestCase;

class ResizeTest extends FilterTestCase
{
    /**
     * @covers \Symfony\Component\Image\Filter\Basic\Resize::apply
     *
     * @dataProvider getDataSet
     *
     * @param BoxInterface $size
     */
    public function testShouldResizeImageAndReturnResult(BoxInterface $size)
    {
        $image = $this->getImage();

        $image->expects($this->once())
            ->method('resize')
            ->with($size)
            ->will($this->returnValue($image));

        $command = new Resize($size);

        $this->assertSame($image, $command->apply($image));
    }

    /**
     * Data provider for testShouldResizeImageAndReturnResult.
     *
     * @return array
     */
    public function getDataSet()
    {
        return array(
            array(new Box(50, 15)),
            array(new Box(300, 25)),
            array(new Box(123, 23)),
            array(new Box(45, 23)),
        );
    }
}
