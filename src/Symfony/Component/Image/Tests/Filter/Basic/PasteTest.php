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

use Symfony\Component\Image\Image\Point;
use Symfony\Component\Image\Filter\Basic\Paste;
use Symfony\Component\Image\Tests\Filter\FilterTestCase;

class PasteTest extends FilterTestCase
{
    public function testShouldFlipImage()
    {
        $start   = new Point(0, 0);
        $image   = $this->getImage();
        $toPaste = $this->getImage();
        $filter  = new Paste($toPaste, $start);

        $image->expects($this->once())
            ->method('paste')
            ->with($toPaste, $start)
            ->will($this->returnValue($image));

        $this->assertSame($image, $filter->apply($image));
    }
}
