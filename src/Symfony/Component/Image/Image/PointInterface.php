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

/**
 * The point interface.
 */
interface PointInterface
{
    /**
     * Gets points x coordinate.
     *
     * @return int
     */
    public function getX();

    /**
     * Gets points y coordinate.
     *
     * @return int
     */
    public function getY();

    /**
     * Checks if current coordinate is inside a given box.
     *
     * @param BoxInterface $box
     *
     * @return bool
     */
    public function in(BoxInterface $box);

    /**
     * Returns another point, moved by a given amount from current coordinates.
     *
     * @param int $amount
     *
     * @return ImageInterface
     */
    public function move($amount);

    /**
     * Gets a string representation for the current point.
     *
     * @return string
     */
    public function __toString();
}
