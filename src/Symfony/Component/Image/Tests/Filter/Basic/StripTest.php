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

use Symfony\Component\Image\Filter\Basic\Strip;
use Symfony\Component\Image\Tests\Filter\FilterTestCase;

class StripTest extends FilterTestCase
{
    public function testShouldStripImage()
    {
        $image  = $this->getImage();
        $filter = new Strip();

        $image->expects($this->once())
            ->method('strip')
            ->will($this->returnValue($image));

        $this->assertSame($image, $filter->apply($image));
    }
}
