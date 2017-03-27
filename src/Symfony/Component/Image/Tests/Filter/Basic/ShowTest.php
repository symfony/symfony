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

use Symfony\Component\Image\Filter\Basic\Show;
use Symfony\Component\Image\Tests\Filter\FilterTestCase;

class ShowTest extends FilterTestCase
{
    public function testShouldShowImageAndReturnResult()
    {
        $image   = $this->getImage();
        $format  = 'jpg';
        $command = new Show($format);

        $image->expects($this->once())
            ->method('show')
            ->with($format)
            ->will($this->returnValue($image));

        $this->assertSame($image, $command->apply($image));
    }
}
