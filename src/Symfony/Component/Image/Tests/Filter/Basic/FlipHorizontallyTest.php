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

use Symfony\Component\Image\Filter\Basic\FlipHorizontally;
use Symfony\Component\Image\Tests\Filter\FilterTestCase;

class FlipHorizontallyTest extends FilterTestCase
{
    public function testShouldFlipImage()
    {
        $image = $this->getImage();
        $filter = new FlipHorizontally();

        $image->expects($this->once())
            ->method('flipHorizontally')
            ->will($this->returnValue($image));

        $this->assertSame($image, $filter->apply($image));
    }
}
