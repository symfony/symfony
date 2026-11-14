<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\TuiHtml\Style;

use Symfony\Component\Tui\Exception\InvalidArgumentException;

/**
 * The size of one terminal cell in device pixels.
 *
 * It converts CSS pixels into cells and gives the pixel density of the
 * terminal: the CSS reference font is 16px tall, so a cell 32 pixels tall
 * has a density of 2dppx.
 *
 * @experimental
 */
final class CellSize
{
    public const REFERENCE_HEIGHT = 16;

    public function __construct(public readonly int $width, public readonly int $height)
    {
        if ($width < 1 || $height < 1) {
            throw new InvalidArgumentException(\sprintf('A cell must be at least 1x1 pixel, got %dx%d.', $width, $height));
        }
    }

    public static function default(): self
    {
        return new self(8, self::REFERENCE_HEIGHT);
    }

    /**
     * Returns the number of device pixels per CSS pixel.
     */
    public function dppx(): float
    {
        return $this->height / self::REFERENCE_HEIGHT;
    }
}
