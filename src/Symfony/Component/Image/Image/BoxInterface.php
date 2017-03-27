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
 * Interface for a box
 */
interface BoxInterface
{
    /**
     * Gets current image height
     *
     * @return integer
     */
    public function getHeight();

    /**
     * Gets current image width
     *
     * @return integer
     */
    public function getWidth();

    /**
     * Creates new BoxInterface instance with ratios applied to both sides
     *
     * @param float $ratio
     *
     * @return BoxInterface
     */
    public function scale($ratio);

    /**
     * Creates new BoxInterface, adding given size to both sides
     *
     * @param integer $size
     *
     * @return BoxInterface
     */
    public function increase($size);

    /**
     * Checks whether current box can fit given box at a given start position,
     * start position defaults to top left corner xy(0,0)
     *
     * @param BoxInterface   $box
     * @param PointInterface $start
     *
     * @return Boolean
     */
    public function contains(BoxInterface $box, PointInterface $start = null);

    /**
     * Gets current box square, useful for getting total number of pixels in a
     * given box
     *
     * @return integer
     */
    public function square();

    /**
     * Returns a string representation of the current box
     *
     * @return string
     */
    public function __toString();

    /**
     * Resizes box to given width, constraining proportions and returns the new box
     *
     * @param integer $width
     *
     * @return BoxInterface
     */
    public function widen($width);

    /**
     * Resizes box to given height, constraining proportions and returns the new box
     *
     * @param integer $height
     *
     * @return BoxInterface
     */
    public function heighten($height);
}
