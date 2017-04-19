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

use Symfony\Component\Image\Image\Palette\CMYK as CMYKPalette;
use Symfony\Component\Image\Exception\RuntimeException;
use Symfony\Component\Image\Exception\InvalidArgumentException;

final class CMYK implements ColorInterface
{
    /**
     * @var int
     */
    private $c;

    /**
     * @var int
     */
    private $m;

    /**
     * @var int
     */
    private $y;

    /**
     * @var int
     */
    private $k;

    /**
     * @var CMYK
     */
    private $palette;

    public function __construct(CMYKPalette $palette, array $color)
    {
        $this->palette = $palette;
        $this->setColor($color);
    }

    /**
     * {@inheritdoc}
     */
    public function getValue($component)
    {
        switch ($component) {
            case ColorInterface::COLOR_CYAN:
                return $this->getCyan();
            case ColorInterface::COLOR_MAGENTA:
                return $this->getMagenta();
            case ColorInterface::COLOR_YELLOW:
                return $this->getYellow();
            case ColorInterface::COLOR_KEYLINE:
                return $this->getKeyline();
            default:
                throw new InvalidArgumentException(sprintf('Color component %s is not valid', $component));
        }
    }

    /**
     * Returns Cyan value of the color.
     *
     * @return int
     */
    public function getCyan()
    {
        return $this->c;
    }

    /**
     * Returns Magenta value of the color.
     *
     * @return int
     */
    public function getMagenta()
    {
        return $this->m;
    }

    /**
     * Returns Yellow value of the color.
     *
     * @return int
     */
    public function getYellow()
    {
        return $this->y;
    }

    /**
     * Returns Key value of the color.
     *
     * @return int
     */
    public function getKeyline()
    {
        return $this->k;
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
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function dissolve($alpha)
    {
        throw new RuntimeException('CMYK does not support dissolution');
    }

    /**
     * {@inheritdoc}
     */
    public function lighten($shade)
    {
        return $this->palette->color(
            array(
                $this->c,
                $this->m,
                $this->y,
                max(0, $this->k - $shade),
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function darken($shade)
    {
        return $this->palette->color(
            array(
                $this->c,
                $this->m,
                $this->y,
                min(100, $this->k + $shade),
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function grayscale()
    {
        $color = array(
            $this->c * (1 - $this->k / 100) + $this->k,
            $this->m * (1 - $this->k / 100) + $this->k,
            $this->y * (1 - $this->k / 100) + $this->k,
        );

        $gray = min(100, round(0.299 * $color[0] + 0.587 * $color[1] + 0.114 * $color[2]));

        return $this->palette->color(array($gray, $gray, $gray, $this->k));
    }

    /**
     * {@inheritdoc}
     */
    public function isOpaque()
    {
        return true;
    }

    /**
     * Returns hex representation of the color.
     *
     * @return string
     */
    public function __toString()
    {
        return sprintf('cmyk(%d%%, %d%%, %d%%, %d%%)', $this->c, $this->m, $this->y, $this->k);
    }

    /**
     * Performs checks for color validity (an of array(C, M, Y, K)).
     *
     * @param array $color
     *
     * @throws InvalidArgumentException
     */
    private function setColor(array $color)
    {
        if (count($color) !== 4) {
            throw new InvalidArgumentException('Color argument must look like array(C, M, Y, K), where C, M, Y, K are the integer values between 0 and 255 for cyan, magenta, yellow and black color indexes accordingly');
        }

        $colors = array_values($color);
        array_walk($colors, function ($color) {
            return max(0, min(100, $color));
        });

        list($this->c, $this->m, $this->y, $this->k) = $colors;
    }
}
