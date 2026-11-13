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

/**
 * Where the background of a box is painted, and how its image is sized, placed and repeated, like CSS
 * background-clip, background-origin, background-size, background-position and background-repeat.
 *
 * Sizes and offsets are in cells; a size is null for auto: the size of an image, or of the positioning area for a
 * gradient, which has none of its own.
 *
 * @experimental
 */
final class BackgroundLayout
{
    public const BORDER_BOX = 'border-box';
    public const PADDING_BOX = 'padding-box';
    public const CONTENT_BOX = 'content-box';
    /** The background is that of the glyphs: their color, the cells keeping what is behind them */
    public const TEXT = 'text';
    public const REPEAT = 'repeat';
    public const NO_REPEAT = 'no-repeat';
    public const SPACE = 'space';
    public const ROUND = 'round';

    /**
     * @param self::BORDER_BOX|self::PADDING_BOX|self::CONTENT_BOX|self::TEXT                                                   $clip     The cells painted
     * @param self::BORDER_BOX|self::PADDING_BOX|self::CONTENT_BOX                                                              $origin   The positioning area of the image
     * @param array{?BackgroundLength, ?BackgroundLength}                                                                       $size     The width and the height of the image, null for auto
     * @param array{BackgroundLength, BackgroundLength}                                                                         $position The left and top offsets of the image in its positioning area
     * @param array{self::REPEAT|self::NO_REPEAT|self::SPACE|self::ROUND, self::REPEAT|self::NO_REPEAT|self::SPACE|self::ROUND} $repeat   Horizontally and vertically
     * @param 'cover'|'contain'|null                                                                                            $fit      How an image is sized to the positioning area, keeping its proportions, like background-size: cover; null for $size
     */
    public function __construct(public readonly string $clip = self::BORDER_BOX, public readonly string $origin = self::PADDING_BOX, public readonly array $size = [null, null], public readonly array $position = [new BackgroundLength(0.0), new BackgroundLength(0.0)], public readonly array $repeat = [self::REPEAT, self::REPEAT], public readonly ?string $fit = null)
    {
    }
}
