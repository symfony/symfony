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
    public $mimeTypes = 'image/*';
    public $minWidth = null;
    public $maxWidth = null;
    public $maxHeight = null;
    public $minHeight = null;

    public $mimeTypesMessage = 'This file is not a valid image';
    public $notDetectedMessage = 'The size of image could not be detected';
    public $maxWidthMessage = 'The image width is too big ({{ width }}px). Allowed maximum width is {{ maxWidth }}px';
    public $minWidthMessage = 'The image width is too small ({{ width }}px). Minimum width expected is {{ minWidth }}px';
    public $maxHeightMessage = 'The image height is too big ({{ height }}px). Allowed maximum width is {{ maxHeight }}px';
    public $minHeightMessage = 'The image width is too small ({{ height }}px). Minimum height expected is {{ minHeight }}px';
}
