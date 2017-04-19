<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Image\Tests\Filter;

use Symfony\Component\Image\Filter\FilterInterface;
use Symfony\Component\Image\Filter\Transformation;
use Symfony\Component\Image\Image\Box;
use Symfony\Component\Image\Image\ImageInterface;
use Symfony\Component\Image\Image\Point;
use Symfony\Component\Image\Image\ManipulatorInterface;

class TransformationTest extends FilterTestCase
{
    public function testSimpleStack()
    {
        $image = $this->getImage();
        $size = new Box(50, 50);
        $path = sys_get_temp_dir();

        $image->expects($this->once())
            ->method('resize')
            ->with($size)
            ->will($this->returnValue($image));

        $image->expects($this->once())
            ->method('save')
            ->with($path)
            ->will($this->returnValue($image));

        $transformation = new Transformation();
        $this->assertSame($image, $transformation->resize($size)
            ->save($path)
            ->apply($image)
        );
    }

    public function testComplexFlow()
    {
        $image = $this->getImage();
        $clone = $this->getImage();
        $thumbnail = $this->getImage();
        $path = sys_get_temp_dir();
        $size = new Box(50, 50);
        $resize = new Box(200, 200);
        $angle = 90;
        $background = $this->getPalette()->color('fff');

        $image->expects($this->once())
            ->method('resize')
            ->with($resize)
            ->will($this->returnValue($image));

        $image->expects($this->once())
            ->method('copy')
            ->will($this->returnValue($clone));

        $clone->expects($this->once())
            ->method('rotate')
            ->with($angle, $background)
            ->will($this->returnValue($clone));

        $clone->expects($this->once())
            ->method('thumbnail')
            ->with($size, ManipulatorInterface::THUMBNAIL_INSET)
            ->will($this->returnValue($thumbnail));

        $thumbnail->expects($this->once())
            ->method('save')
            ->with($path)
            ->will($this->returnValue($thumbnail));

        $transformation = new Transformation();

        $transformation->resize($resize)
            ->copy()
            ->rotate($angle, $background)
            ->thumbnail($size, ManipulatorInterface::THUMBNAIL_INSET)
            ->save($path);

        $this->assertSame($thumbnail, $transformation->apply($image));
    }

    public function testCropFlipPasteShow()
    {
        $img1 = $this->getImage();
        $img2 = $this->getImage();
        $start = new Point(0, 0);
        $size = new Box(50, 50);

        $img1->expects($this->once())
            ->method('paste')
            ->with($img2, $start)
            ->will($this->returnValue($img1));

        $img1->expects($this->once())
            ->method('show')
            ->with('png')
            ->will($this->returnValue($img1));

        $img2->expects($this->once())
            ->method('flipHorizontally')
            ->will($this->returnValue($img2));

        $img2->expects($this->once())
            ->method('flipVertically')
            ->will($this->returnValue($img2));

        $img2->expects($this->once())
            ->method('crop')
            ->with($start, $size)
            ->will($this->returnValue($img2));

        $transformation2 = new Transformation();
        $transformation2->flipHorizontally()
            ->flipVertically()
            ->crop($start, $size);

        $transformation1 = new Transformation();
        $transformation1->paste($transformation2->apply($img2), $start)
            ->show('png')
            ->apply($img1);
    }

    public function testFilterSorting()
    {
        $filter1 = new TestFilter();
        $filter2 = new TestFilter();
        $filter3 = new TestFilter();

        $transformation1 = new Transformation();
        $transformation1
            ->add($filter1, 5)
            ->add($filter2, -3)
            ->add($filter3);

        $expected1 = array(
            $filter2,
            $filter3,
            $filter1,
        );

        $transformation2 = new Transformation();
        $transformation2
            ->add($filter1)
            ->add($filter2)
            ->add($filter3);

        $expected2 = array(
            $filter1,
            $filter2,
            $filter3,
        );

        $this->assertSame($expected1, $transformation1->getFilters());
        $this->assertSame($expected2, $transformation2->getFilters());
    }

    public function testGetEmptyFilters()
    {
        $transformation = new Transformation();
        $this->assertSame(array(), $transformation->getFilters());
    }
}

class TestFilter implements FilterInterface
{
    /**
     * {@inheritdoc}
     */
    public function apply(ImageInterface $image)
    {
    }
}
