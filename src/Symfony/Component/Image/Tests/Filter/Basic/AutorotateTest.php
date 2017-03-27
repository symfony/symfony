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

USE Symfony\Component\Image\Filter\Basic\Autorotate;
use Symfony\Component\Image\Image\Metadata\MetadataBag;
use Symfony\Component\Image\Tests\Filter\FilterTestCase;

class AutorotateTest extends FilterTestCase
{
    /**
     * @dataProvider provideMetadataAndRotations
     */
    public function testApply($expectedRotation, $hFlipExpected, MetadataBag $metadata)
    {
        $image = $this->getImage();
        $image->expects($this->any())
            ->method('metadata')
            ->will($this->returnValue($metadata));

        if (null === $expectedRotation) {
            $image->expects($this->never())
                ->method('rotate');
        } else {
            $image->expects($this->once())
                ->method('rotate')
                ->with($expectedRotation);
        }

        $image->expects($hFlipExpected ? $this->once() : $this->never())
            ->method('flipHorizontally');

        $filter = new Autorotate($this->getColor());
        $filter->apply($image);
    }

    public function provideMetadataAndRotations()
    {
        return array(
            array(null, false, new MetadataBag(array())),
            array(null, false, new MetadataBag(array('ifd0.Orientation' => null))),
            array(null, false, new MetadataBag(array('ifd0.Orientation' => 0))),
            array(null, false, new MetadataBag(array('ifd0.Orientation' => 1))),
            array(null, true, new MetadataBag(array('ifd0.Orientation' => 2))),
            array(180, false, new MetadataBag(array('ifd0.Orientation' => 3))),
            array(180, true, new MetadataBag(array('ifd0.Orientation' => 4))),
            array(-90, true, new MetadataBag(array('ifd0.Orientation' => 5))),
            array(90, false, new MetadataBag(array('ifd0.Orientation' => 6))),
            array(90, true, new MetadataBag(array('ifd0.Orientation' => 7))),
            array(-90, false, new MetadataBag(array('ifd0.Orientation' => 8))),
        );
    }
}
