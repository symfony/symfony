<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Image\Image;

use Symfony\Component\Image\Exception\InvalidArgumentException;

/**
 * The point class
 */
final class Point implements PointInterface
{
    /**
     * @var integer
     */
    private $x;

    /**
     * @var integer
     */
    private $y;

    /**
     * Constructs a point of coordinates
     *
     * @param integer $x
     * @param integer $y
     *
     * @throws InvalidArgumentException
     */
    public function __construct($x, $y)
    {
        if ($x < 0 || $y < 0) {
            throw new InvalidArgumentException(sprintf('A coordinate cannot be positioned outside of a bounding box (x: %s, y: %s given)', $x, $y));
        }

        $this->x = $x;
        $this->y = $y;
    }

    /**
     * {@inheritdoc}
     */
    public function getX()
    {
        return $this->x;
    }

    /**
     * {@inheritdoc}
     */
    public function getY()
    {
        return $this->y;
    }

    /**
     * {@inheritdoc}
     */
    public function in(BoxInterface $box)
    {
        return $this->x < $box->getWidth() && $this->y < $box->getHeight();
    }

    /**
     * {@inheritdoc}
     */
    public function move($amount)
    {
        return new Point($this->x + $amount, $this->y + $amount);
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        return sprintf('(%d, %d)', $this->x, $this->y);
    }
}
