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
 *
 * @api
 */
class Image extends File
{
    const ERROR_SIZE_NOT_DETECTED = '1ea61424-9a94-46e5-89b1-26fa91dacf55';
    const ERROR_MAX_WIDTH = 'a92d747a-9cd8-4194-bbba-ac9d40f438fe';
    const ERROR_MIN_WIDTH = '52479c81-46e9-4302-b15d-f9c36b6cd6ce';
    const ERROR_MAX_HEIGHT = '57d13e39-13ed-4dde-b562-2d5a8b52ef96';
    const ERROR_MIN_HEIGHT = '48378c83-eaf2-436a-b972-f28ca6cc5e2b';
    const ERROR_MAX_RATIO = '90ddd17c-3beb-4f89-84d2-444734ae4e71';
    const ERROR_MIN_RATIO = '17cd9582-15f0-4238-b778-f72a31b4debe';
    const ERROR_DONT_ALLOW_SQUARE = 'ac8f80d1-9432-419e-859f-10142f6daed9';
    const ERROR_DONT_ALLOW_LANDSCAPE = '2d77d2b1-1c41-405d-9dee-db260bae2fb8';
    const ERROR_DONT_ALLOW_PORTRAIT = '84451f62-d4e2-4ab1-96be-dc7c25e0e4d9';

    public $mimeTypes = 'image/*';
    public $minWidth = null;
    public $maxWidth = null;
    public $maxHeight = null;
    public $minHeight = null;
    public $maxRatio = null;
    public $minRatio = null;
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
