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

use Symfony\Component\Image\Image\Fill\Gradient\Vertical;
use Symfony\Component\Image\Image\Palette\Color\ColorInterface;
use Symfony\Component\Image\Image\Point;

class VerticalTest extends LinearTest
{
    /**
     * (non-PHPdoc).
     *
     * @see Symfony\Component\Image\Image\Fill\Gradient\LinearTest::getEnd()
     */
    protected function getEnd()
    {
        return $this->getColor('fff');
    }

    /**
     * (non-PHPdoc).
     *
     * @see Symfony\Component\Image\Image\Fill\Gradient\LinearTest::getStart()
     */
    protected function getStart()
    {
        return $this->getColor('000');
    }

    /**
     * (non-PHPdoc).
     *
     * @see Symfony\Component\Image\Image\Fill\Gradient\LinearTest::getMask()
     */
    protected function getFill(ColorInterface $start, ColorInterface $end)
    {
        return new Vertical(100, $start, $end);
    }

    /**
     * (non-PHPdoc).
     *
     * @see Symfony\Component\Image\Image\Fill\Gradient\LinearTest::getPointsAndShades()
     */
    public function getPointsAndColors()
    {
        return array(
            array($this->getColor('fff'), new Point(5, 100)),
            array($this->getColor('000'), new Point(15, 0)),
            array($this->getColor(array(128, 128, 128)), new Point(25, 50)),
        );
    }
}
