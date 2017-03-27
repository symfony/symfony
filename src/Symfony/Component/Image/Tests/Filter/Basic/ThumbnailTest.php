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

use Symfony\Component\Image\Filter\Basic\Thumbnail;
use Symfony\Component\Image\Image\Box;
use Symfony\Component\Image\Image\ManipulatorInterface;
use Symfony\Component\Image\Tests\Filter\FilterTestCase;

class ThumbnailTest extends FilterTestCase
{
    public function testShouldMakeAThumbnail()
    {
        $image = $this->getImage();
        $thumbnail = $this->getImage();
        $size = new Box(50, 50);
        $filter = new Thumbnail($size);

        $image->expects($this->once())
            ->method('thumbnail')
            ->with($size, ManipulatorInterface::THUMBNAIL_INSET)
            ->will($this->returnValue($thumbnail));

        $this->assertSame($thumbnail, $filter->apply($image));
    }
}
