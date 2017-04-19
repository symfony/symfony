<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Image\Tests\Image\Point;

use Symfony\Component\Image\Image\Point\Center;
use Symfony\Component\Image\Image\Point;
use Symfony\Component\Image\Image\PointInterface;
use Symfony\Component\Image\Image\Box;
use Symfony\Component\Image\Image\BoxInterface;
use Symfony\Component\Image\Tests\TestCase;

class CenterTest extends TestCase
{
    /**
     * @covers \Symfony\Component\Image\Image\Point\Center::getX
     * @covers \Symfony\Component\Image\Image\Point\Center::getY
     *
     * @dataProvider getSizesAndCoordinates
     *
     * @param \Symfony\Component\Image\Image\BoxInterface   $box
     * @param \Symfony\Component\Image\Image\PointInterface $expected
     */
    public function testShouldGetCenterCoordinates(BoxInterface $box, PointInterface $expected)
    {
        $point = new Center($box);

        $this->assertEquals($expected->getX(), $point->getX());
        $this->assertEquals($expected->getY(), $point->getY());
    }

    /**
     * Data provider for testShouldGetCenterCoordinates.
     *
     * @return array
     */
    public function getSizesAndCoordinates()
    {
        return array(
            array(new Box(10, 15), new Point(5, 8)),
            array(new Box(40, 23), new Point(20, 12)),
            array(new Box(14, 8), new Point(7, 4)),
        );
    }

    /**
     * @covers \Symfony\Component\Image\Image\Point::getX
     * @covers \Symfony\Component\Image\Image\Point::getY
     * @covers \Symfony\Component\Image\Image\Point::move
     *
     * @dataProvider getMoves
     *
     * @param \Symfony\Component\Image\Image\BoxInterface $box
     * @param int                                         $move
     * @param int                                         $x1
     * @param int                                         $y1
     */
    public function testShouldMoveByGivenAmount(BoxInterface $box, $move, $x1, $y1)
    {
        $point = new Center($box);
        $shift = $point->move($move);

        $this->assertEquals($x1, $shift->getX());
        $this->assertEquals($y1, $shift->getY());
    }

    public function getMoves()
    {
        return array(
            array(new Box(10, 20), 5, 10, 15),
            array(new Box(5, 37), 2, 5, 21),
        );
    }

    /**
     * @covers \Symfony\Component\Image\Image\Point\Center::__toString
     */
    public function testToString()
    {
        $this->assertEquals('(50, 50)', (string) new Center(new Box(100, 100)));
    }
}
