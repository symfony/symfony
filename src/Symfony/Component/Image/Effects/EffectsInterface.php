<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Image\Effects;

use Symfony\Component\Image\Exception\RuntimeException;
use Symfony\Component\Image\Image\Palette\Color\ColorInterface;

interface EffectsInterface
{
    /**
     * Apply gamma correction.
     *
     * @param float $correction
     *
     * @return EffectsInterface
     *
     * @throws RuntimeException
     */
    public function gamma($correction);

    /**
     * Invert the colors of the image.
     *
     * @return EffectsInterface
     *
     * @throws RuntimeException
     */
    public function negative();

    /**
     * Grayscale the image.
     *
     * @return EffectsInterface
     *
     * @throws RuntimeException
     */
    public function grayscale();

    /**
     * Colorize the image.
     *
     * @param ColorInterface $color
     *
     * @return EffectsInterface
     *
     * @throws RuntimeException
     */
    public function colorize(ColorInterface $color);

    /**
     * Sharpens the image.
     *
     * @return EffectsInterface
     *
     * @throws RuntimeException
     */
    public function sharpen();

    /**
     * Blur the image.
     *
     * @param float|int $sigma
     *
     * @return EffectsInterface
     *
     * @throws RuntimeException
     */
    public function blur($sigma);
}
