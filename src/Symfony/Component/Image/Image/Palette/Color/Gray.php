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

use Symfony\Component\Image\Image\Palette\Grayscale;
use Symfony\Component\Image\Exception\InvalidArgumentException;

final class Gray implements ColorInterface
{
    /**
     * @var int
     */
    private $gray;

    /**
     * @var int
     */
    private $alpha;

    /**
     * @var Grayscale
     */
    private $palette;

    public function __construct(Grayscale $palette, array $color, $alpha)
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
            case ColorInterface::COLOR_GRAY:
                return $this->getGray();
            default:
                throw new InvalidArgumentException(sprintf('Color component %s is not valid', $component));
        }
    }

    /**
     * Returns Gray value of the color.
     *
     * @return int
     */
    public function getGray()
    {
        return $this->gray;
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
        return $this->palette->color(
            array($this->gray), $this->alpha + $alpha
        );
    }

    /**
     * {@inheritdoc}
     */
    public function lighten($shade)
    {
        return $this->palette->color(array(min(255, $this->gray + $shade)), $this->alpha);
    }

    /**
     * {@inheritdoc}
     */
    public function darken($shade)
    {
        return $this->palette->color(array(max(0, $this->gray - $shade)), $this->alpha);
    }

    /**
     * {@inheritdoc}
     */
    public function grayscale()
    {
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function isOpaque()
    {
        return 100 === $this->alpha;
    }

    /**
     * Returns hex representation of the color.
     *
     * @return string
     */
    public function __toString()
    {
        return sprintf('#%02x%02x%02x', $this->gray, $this->gray, $this->gray);
    }

    /**
     * Performs checks for validity of given alpha value and sets it.
     *
     * @param int $alpha
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
     * Performs checks for color validity (array of array(gray)).
     *
     * @param array $color
     *
     * @throws InvalidArgumentException
     */
    private function setColor(array $color)
    {
        if (count($color) !== 1) {
            throw new InvalidArgumentException('Color argument must look like array(gray), where gray is the integer value between 0 and 255 for the grayscale');
        }

        list($this->gray) = array_values($color);
    }
}
