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

use Symfony\Component\Image\Image\Fill\Gradient\Horizontal;
use Symfony\Component\Image\Image\Palette\Color\ColorInterface;
use Symfony\Component\Image\Image\Point;

class HorizontalTest extends LinearTest
{
    /**
     * (non-PHPdoc)
     * @see Symfony\Component\Image\Image\Fill\Gradient\LinearTest::getEnd()
     */
    protected function getEnd()
    {
        return $this->getColor('fff');
    }

    /**
     * (non-PHPdoc)
     * @see Symfony\Component\Image\Image\Fill\Gradient\LinearTest::getStart()
     */
    protected function getStart()
    {
        return $this->getColor('000');
    }

    /**
     * (non-PHPdoc)
     * @see Symfony\Component\Image\Image\Fill\Gradient\LinearTest::getMask()
     */
    protected function getFill(ColorInterface $start, ColorInterface $end)
    {
        return new Horizontal(100, $start, $end);
    }

    /**
     * (non-PHPdoc)
     * @see Symfony\Component\Image\Image\Fill\Gradient\LinearTest::getPointsAndShades()
     */
    public function getPointsAndColors()
    {
        return array(
            array($this->getColor('fff'), new Point(100, 5)),
            array($this->getColor('000'), new Point(0, 15)),
            array($this->getColor(array(128, 128, 128)), new Point(50, 25))
        );
    }
}
