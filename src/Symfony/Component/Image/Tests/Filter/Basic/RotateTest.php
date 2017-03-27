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

use Symfony\Component\Image\Filter\Basic\Rotate;
use Symfony\Component\Image\Tests\Filter\FilterTestCase;

class RotateTest extends FilterTestCase
{
    public function testShouldRotateImageAndReturnResult()
    {
        $image   = $this->getImage();
        $angle   = 90;
        $command = new Rotate($angle);

        $image->expects($this->once())
            ->method('rotate')
            ->with($angle)
            ->will($this->returnValue($image));

        $this->assertSame($image, $command->apply($image));
    }
}
