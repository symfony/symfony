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

use Symfony\Component\Image\Filter\Basic\Save;
use Symfony\Component\Image\Tests\Filter\FilterTestCase;

class SaveTest extends FilterTestCase
{
    public function testShouldSaveImageAndReturnResult()
    {
        $image   = $this->getImage();
        $path    = '/path/to/image.jpg';
        $command = new Save($path);

        $image->expects($this->once())
            ->method('save')
            ->with($path)
            ->will($this->returnValue($image));

        $this->assertSame($image, $command->apply($image));
    }
}
