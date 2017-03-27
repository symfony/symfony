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

use Symfony\Component\Image\Tests\Filter\FilterTestCase;
use Symfony\Component\Image\Filter\Basic\Copy;

class CopyTest extends FilterTestCase
{
    public function testShouldCopyAndReturnResultingImage()
    {
        $command = new Copy();
        $image   = $this->getImage();
        $clone   = $this->getImage();

        $image->expects($this->once())
            ->method('copy')
            ->will($this->returnValue($clone));

        $this->assertSame($clone, $command->apply($image));
    }
}
