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

use Symfony\Component\Image\Image\Box;
use Symfony\Component\Image\Image\BoxInterface;
use Symfony\Component\Image\Filter\Basic\Crop;
use Symfony\Component\Image\Image\Point;
use Symfony\Component\Image\Image\PointInterface;
use Symfony\Component\Image\Tests\Filter\FilterTestCase;

class CropTest extends FilterTestCase
{
    /**
     * @covers \Symfony\Component\Image\Filter\Basic\Crop::apply
     *
     * @dataProvider getDataSet
     *
     * @param PointInterface $start
     * @param BoxInterface   $size
     */
    public function testShouldApplyCropAndReturnResult(PointInterface $start, BoxInterface $size)
    {
        $image = $this->getImage();

        $command = new Crop($start, $size);

        $image->expects($this->once())
            ->method('crop')
            ->with($start, $size)
            ->will($this->returnValue($image));

        $this->assertSame($image, $command->apply($image));
    }

    /**
     * Provides coordinates and sizes for testShouldApplyCropAndReturnResult.
     *
     * @return array
     */
    public function getDataSet()
    {
        return array(
            array(new Point(0, 0), new Box(40, 50)),
            array(new Point(0, 15), new Box(50, 32)),
        );
    }
}
