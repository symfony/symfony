<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Image\Image\Palette\Color;

use Symfony\Component\Image\Image\Palette\RGB as RGBPalette;
use Symfony\Component\Image\Exception\InvalidArgumentException;

final class RGB implements ColorInterface
{
    /**
     * @var integer
     */
    private $r;

    /**
     * @var integer
     */
    private $g;

    /**
     * @var integer
     */
    private $b;

    /**
     * @var integer
     */
    private $alpha;

    /**
     *
     * @var RGBPalette
     */
    private $palette;

    public function __construct(RGBPalette $palette, array $color, $alpha)
    {
        $this->palette = $palette;
        $this->setColor($color);
        $this->setAlpha($alpha);
    }

    /**
     * {@inheritdoc}
     */
    public function getValue($component)
    {
        switch ($component) {
            case ColorInterface::COLOR_RED:
                return $this->getRed();
            case ColorInterface::COLOR_GREEN:
                return $this->getGreen();
            case ColorInterface::COLOR_BLUE:
                return $this->getBlue();
            default:
                throw new InvalidArgumentException(sprintf('Color component %s is not valid', $component));
        }
    }

    /**
     * Returns RED value of the color
     *
     * @return integer
     */
    public function getRed()
    {
        return $this->r;
    }

    /**
     * Returns GREEN value of the color
     *
     * @return integer
     */
    public function getGreen()
    {
        return $this->g;
    }

    /**
     * Returns BLUE value of the color
     *
     * @return integer
     */
    public function getBlue()
    {
        return $this->b;
    }

    /**
     * {@inheritdoc}
     */
    public function getPalette()
    {
        return $this->palette;
    }

    /**
     * {@inheritdoc}
     */
    public function getAlpha()
    {
        return $this->alpha;
    }

    /**
     * {@inheritdoc}
     */
    public function dissolve($alpha)
    {
        return $this->palette->color(array($this->r, $this->g, $this->b), $this->alpha + $alpha);
    }

    /**
     * {@inheritdoc}
     */
    public function lighten($shade)
    {
        return $this->palette->color(
            array(
                min(255, $this->r + $shade),
                min(255, $this->g + $shade),
                min(255, $this->b + $shade),
            ), $this->alpha
        );
    }

    /**
     * {@inheritdoc}
     */
    public function darken($shade)
    {
        return $this->palette->color(
            array(
                max(0, $this->r - $shade),
                max(0, $this->g - $shade),
                max(0, $this->b - $shade),
            ), $this->alpha
        );
    }

    /**
     * {@inheritdoc}
     */
    public function grayscale()
    {
        $gray = min(255, round(0.299 * $this->getRed() + 0.114 * $this->getBlue() + 0.587 * $this->getGreen()));

        return $this->palette->color(array($gray, $gray, $gray), $this->alpha);
    }

    /**
     * {@inheritdoc}
     */
    public function isOpaque()
    {
        return 100 === $this->alpha;
    }

    /**
     * Returns hex representation of the color
     *
     * @return string
     */
    public function __toString()
    {
        return sprintf('#%02x%02x%02x', $this->r, $this->g, $this->b);
    }

    /**
     * Internal
     *
     * Performs checks for validity of given alpha value and sets it
     *
     * @param integer $alpha
     *
     * @throws InvalidArgumentException
     */
    private function setAlpha($alpha)
    {
        if (!is_int($alpha) || $alpha < 0 || $alpha > 100) {
            throw new InvalidArgumentException(sprintf('Alpha must be an integer between 0 and 100, %s given', $alpha));
        }

        $this->alpha = $alpha;
    }

    /**
     * Internal
     *
     * Performs checks for color validity (array of array(R, G, B))
     *
     * @param array $color
     *
     * @throws InvalidArgumentException
     */
    private function setColor(array $color)
    {
        if (count($color) !== 3) {
            throw new InvalidArgumentException('Color argument must look like array(R, G, B), where R, G, B are the integer values between 0 and 255 for red, green and blue color indexes accordingly');
        }

        list($this->r, $this->g, $this->b) = array_values($color);
    }
}
