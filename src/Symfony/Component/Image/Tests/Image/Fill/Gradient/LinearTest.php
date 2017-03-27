<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Image\Tests\Image\Fill\Gradient;

use Symfony\Component\Image\Image\Palette\RGB;
use Symfony\Component\Image\Image\Palette\Color\ColorInterface;
use Symfony\Component\Image\Image\PointInterface;
use Symfony\Component\Image\Tests\TestCase;

abstract class LinearTest extends TestCase
{
    /**
     * @var \Symfony\Component\Image\Image\Fill\FillInterface
     */
    private $fill;

    /**
     * @var ColorInterface
     */
    private $start;

    /**
     * @var ColorInterface
     */
    private $end;
    protected $palette;

    protected function setUp()
    {
        $this->start = $this->getStart();
        $this->end   = $this->getEnd();
        $this->fill  = $this->getFill($this->start, $this->end);
    }

    /**
     * @dataProvider getPointsAndColors
     *
     * @param integer                      $shade
     * @param \Symfony\Component\Image\Image\PointInterface $position
     */
    public function testShouldProvideCorrectColorsValues(ColorInterface $color, PointInterface $position)
    {
        $this->assertEquals($color, $this->fill->getColor($position));
    }

    /**
     * @covers \Symfony\Component\Image\Image\Fill\Gradient\Linear::getStart
     * @covers \Symfony\Component\Image\Image\Fill\Gradient\Linear::getEnd
     */
    public function testShouldReturnCorrectStartAndEnd()
    {
        $this->assertSame($this->start, $this->fill->getStart());
        $this->assertSame($this->end, $this->fill->getEnd());
    }

    protected function getColor($color)
    {
        static $palette;

        if (!$palette) {
            $palette = new RGB();
        }

        return $palette->color($color);
    }

    /**
     * @param ColorInterface $start
     * @param ColorInterface $end
     *
     * @return Symfony\Component\Image\Image\Fill\FillInterface
     */
    abstract protected function getFill(ColorInterface $start, ColorInterface $end);

    /**
     * @return ColorInterface
     */
    abstract protected function getStart();

    /**
     * @return ColorInterface
     */
    abstract protected function getEnd();

    /**
     * @return array
     */
    abstract public function getPointsAndColors();
}
