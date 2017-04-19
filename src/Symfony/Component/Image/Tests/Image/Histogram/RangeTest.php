<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Image\Tests\Image\Histogram;

use Symfony\Component\Image\Image\Histogram\Range;
use Symfony\Component\Image\Tests\TestCase;

class RangeTest extends TestCase
{
    private $start = 0;
    private $end = 63;

    /**
     * @dataProvider getExpectedResultsAndValues
     *
     * @param bool $contains
     * @param int  $value
     */
    public function testShouldDetermineIfContainsValue($contains, $value)
    {
        $range = new Range($this->start, $this->end);

        $this->assertEquals($contains, $range->contains($value));
    }

    public function getExpectedResultsAndValues()
    {
        return array(
            array(true, 12),
            array(true, 0),
            array(false, 128),
            array(false, 63),
        );
    }

    /**
     * @expectedException \Symfony\Component\Image\Exception\OutOfBoundsException
     */
    public function testShouldThrowExceptionIfEndIsSmallerThanStart()
    {
        new Range($this->end, $this->start);
    }
}
