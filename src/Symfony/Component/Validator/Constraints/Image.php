<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Constraints;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 *
 * @author Benjamin Dulau <benjamin.dulau@gmail.com>
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class Image extends File
{
    // Don't reuse values used in File

    const SIZE_NOT_DETECTED_ERROR = 10;
    const TOO_WIDE_ERROR = 11;
    const TOO_NARROW_ERROR = 12;
    const TOO_HIGH_ERROR = 13;
    const TOO_LOW_ERROR = 14;
    const RATIO_TOO_BIG_ERROR = 15;
    const RATIO_TOO_SMALL_ERROR = 16;
    const SQUARE_NOT_ALLOWED_ERROR = 17;
    const LANDSCAPE_NOT_ALLOWED_ERROR = 18;
    const PORTRAIT_NOT_ALLOWED_ERROR = 19;

    // Include the mapping from the base class

    protected static $errorNames = array(
        self::NOT_FOUND_ERROR => 'NOT_FOUND_ERROR',
        self::NOT_READABLE_ERROR => 'NOT_READABLE_ERROR',
        self::EMPTY_ERROR => 'EMPTY_ERROR',
        self::TOO_LARGE_ERROR => 'TOO_LARGE_ERROR',
        self::INVALID_MIME_TYPE_ERROR => 'INVALID_MIME_TYPE_ERROR',
        self::SIZE_NOT_DETECTED_ERROR => 'SIZE_NOT_DETECTED_ERROR',
        self::TOO_WIDE_ERROR => 'TOO_WIDE_ERROR',
        self::TOO_NARROW_ERROR => 'TOO_NARROW_ERROR',
        self::TOO_HIGH_ERROR => 'TOO_HIGH_ERROR',
        self::TOO_LOW_ERROR => 'TOO_LOW_ERROR',
        self::RATIO_TOO_BIG_ERROR => 'RATIO_TOO_BIG_ERROR',
        self::RATIO_TOO_SMALL_ERROR => 'RATIO_TOO_SMALL_ERROR',
        self::SQUARE_NOT_ALLOWED_ERROR => 'SQUARE_NOT_ALLOWED_ERROR',
        self::LANDSCAPE_NOT_ALLOWED_ERROR => 'LANDSCAPE_NOT_ALLOWED_ERROR',
        self::PORTRAIT_NOT_ALLOWED_ERROR => 'PORTRAIT_NOT_ALLOWED_ERROR',
    );

    public $mimeTypes = 'image/*';
    public $minWidth;
    public $maxWidth;
    public $maxHeight;
    public $minHeight;
    public $maxRatio;
    public $minRatio;
    public $allowSquare = true;
    public $allowLandscape = true;
    public $allowPortrait = true;

    // The constant for a wrong MIME type is taken from the parent class.
    public $mimeTypesMessage = 'This file is not a valid image.';
    public $sizeNotDetectedMessage = 'The size of the image could not be detected.';
    public $maxWidthMessage = 'The image width is too big ({{ width }}px). Allowed maximum width is {{ max_width }}px.';
    public $minWidthMessage = 'The image width is too small ({{ width }}px). Minimum width expected is {{ min_width }}px.';
    public $maxHeightMessage = 'The image height is too big ({{ height }}px). Allowed maximum height is {{ max_height }}px.';
    public $minHeightMessage = 'The image height is too small ({{ height }}px). Minimum height expected is {{ min_height }}px.';
    public $maxRatioMessage = 'The image ratio is too big ({{ ratio }}). Allowed maximum ratio is {{ max_ratio }}.';
    public $minRatioMessage = 'The image ratio is too small ({{ ratio }}). Minimum ratio expected is {{ min_ratio }}.';
    public $allowSquareMessage = 'The image is square ({{ width }}x{{ height }}px). Square images are not allowed.';
    public $allowLandscapeMessage = 'The image is landscape oriented ({{ width }}x{{ height }}px). Landscape oriented images are not allowed.';
    public $allowPortraitMessage = 'The image is portrait oriented ({{ width }}x{{ height }}px). Portrait oriented images are not allowed.';
}
