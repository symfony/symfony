<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Image\Tests\Image;

use Symfony\Component\Image\Image\PointInterface;
use Symfony\Component\Image\Image\BoxInterface;
use Symfony\Component\Image\Image\Box;
use Symfony\Component\Image\Image\Point;
use Symfony\Component\Image\Tests\TestCase;

class BoxTest extends TestCase
{
    /**
     * @covers \Symfony\Component\Image\Image\Box::getWidth
     * @covers \Symfony\Component\Image\Image\Box::getHeight
     *
     * @dataProvider getSizes
     *
     * @param integer $width
     * @param integer $height
     */
    public function testShouldAssignWidthAndHeight($width, $height)
    {
        $size = new Box($width, $height);

        $this->assertEquals($width, $size->getWidth());
        $this->assertEquals($height, $size->getHeight());
    }

    /**
     * Data provider for testShouldAssignWidthAndHeight
     *
     * @return array
     */
    public function getSizes()
    {
        return array(
            array(1, 1),
            array(10, 10),
            array(15, 36)
        );
    }

    /**
     * @covers \Symfony\Component\Image\Image\Box::__construct
     *
     * @expectedException \Symfony\Component\Image\Exception\InvalidArgumentException
     *
     * @dataProvider getInvalidSizes
     *
     * @param integer $width
     * @param integer $height
     */
    public function testShouldThrowExceptionOnInvalidSize($width, $height)
    {
        new Box($width, $height);
    }

    /**
     * Data provider for testShouldThrowExceptionOnInvalidSize
     *
     * @return array
     */
    public function getInvalidSizes()
    {
        return array(
            array(0, 0),
            array(15, 0),
            array(0, 25),
            array(-1, 4)
        );
    }

    /**
     * @covers \Symfony\Component\Image\Image\Box::contains
     *
     * @dataProvider getSizeBoxStartAndExpected
     *
     * @param BoxInterface   $size
     * @param BoxInterface   $box
     * @param PointInterface $start
     * @param Boolean        $expected
     */
    public function testShouldDetermineIfASizeContainsABoxAtAStartPosition(
        BoxInterface       $size,
        BoxInterface       $box,
        PointInterface $start,
        $expected
    ) {
        $this->assertEquals($expected, $size->contains($box, $start));
    }

    /**
     * Data provider for testShouldDetermineIfASizeContainsABoxAtAStartPosition
     *
     * @return array
     */
    public function getSizeBoxStartAndExpected()
    {
        return array(
            array(new Box(50, 50), new Box(30, 30), new Point(0, 0), true),
            array(new Box(50, 50), new Box(30, 30), new Point(20, 20), true),
            array(new Box(50, 50), new Box(30, 30), new Point(21, 21), false),
            array(new Box(50, 50), new Box(30, 30), new Point(21, 20), false),
            array(new Box(50, 50), new Box(30, 30), new Point(20, 22), false),
        );
    }

    /**
     * @cover Symfony\Component\Image\Image\Box::__toString
     */
    public function testToString()
    {
        $this->assertEquals('100x100 px', (string) new Box(100, 100));
    }

    public function testShouldScaleBox()
    {
        $box = new Box(10, 20);

        $this->assertEquals(new Box(100, 200), $box->scale(10));
    }

    public function testShouldIncreaseBox()
    {
        $box = new Box(10, 20);

        $this->assertEquals(new Box(15, 25), $box->increase(5));
    }

    /**
     * @dataProvider getSizesAndSquares
     *
     * @param integer $width
     * @param integer $height
     * @param integer $square
     */
    public function testShouldCalculateSquare($width, $height, $square)
    {
        $box = new Box($width, $height);

        $this->assertEquals($square, $box->square());
    }

    public function getSizesAndSquares()
    {
        return array(
            array(10, 15, 150),
            array(2, 2, 4),
            array(9, 8, 72),
        );
    }

    /**
     * @dataProvider getDimensionsAndTargets
     *
     * @param integer $width
     * @param integer $height
     * @param integer $targetWidth
     * @param integer $targetHeight
     */
    public function testShouldResizeToTargetWidthAndHeight($width, $height, $targetWidth, $targetHeight)
    {
        $box = new Box($width, $height);
        $expected = new Box($targetWidth, $targetHeight);

        $this->assertEquals($expected, $box->widen($targetWidth));
        $this->assertEquals($expected, $box->heighten($targetHeight));
    }

    public function getDimensionsAndTargets()
    {
        return array(
            array(10, 50, 50, 250),
            array(25, 40, 50, 80),
        );
    }
}
