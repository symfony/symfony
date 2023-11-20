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
 * @author Benjamin Dulau <benjamin.dulau@gmail.com>
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Image extends File
{
    public const SIZE_NOT_DETECTED_ERROR = '6d55c3f4-e58e-4fe3-91ee-74b492199956';
    public const TOO_WIDE_ERROR = '7f87163d-878f-47f5-99ba-a8eb723a1ab2';
    public const TOO_NARROW_ERROR = '9afbd561-4f90-4a27-be62-1780fc43604a';
    public const TOO_HIGH_ERROR = '7efae81c-4877-47ba-aa65-d01ccb0d4645';
    public const TOO_LOW_ERROR = 'aef0cb6a-c07f-4894-bc08-1781420d7b4c';
    public const TOO_FEW_PIXEL_ERROR = '1b06b97d-ae48-474e-978f-038a74854c43';
    public const TOO_MANY_PIXEL_ERROR = 'ee0804e8-44db-4eac-9775-be91aaf72ce1';
    public const RATIO_TOO_BIG_ERROR = '70cafca6-168f-41c9-8c8c-4e47a52be643';
    public const RATIO_TOO_SMALL_ERROR = '59b8c6ef-bcf2-4ceb-afff-4642ed92f12e';
    public const SQUARE_NOT_ALLOWED_ERROR = '5d41425b-facb-47f7-a55a-de9fbe45cb46';
    public const LANDSCAPE_NOT_ALLOWED_ERROR = '6f895685-7cf2-4d65-b3da-9029c5581d88';
    public const PORTRAIT_NOT_ALLOWED_ERROR = '65608156-77da-4c79-a88c-02ef6d18c782';
    public const CORRUPTED_IMAGE_ERROR = '5d4163f3-648f-4e39-87fd-cc5ea7aad2d1';

    // Include the mapping from the base class

    protected const ERROR_NAMES = [
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
        self::TOO_FEW_PIXEL_ERROR => 'TOO_FEW_PIXEL_ERROR',
        self::TOO_MANY_PIXEL_ERROR => 'TOO_MANY_PIXEL_ERROR',
        self::RATIO_TOO_BIG_ERROR => 'RATIO_TOO_BIG_ERROR',
        self::RATIO_TOO_SMALL_ERROR => 'RATIO_TOO_SMALL_ERROR',
        self::SQUARE_NOT_ALLOWED_ERROR => 'SQUARE_NOT_ALLOWED_ERROR',
        self::LANDSCAPE_NOT_ALLOWED_ERROR => 'LANDSCAPE_NOT_ALLOWED_ERROR',
        self::PORTRAIT_NOT_ALLOWED_ERROR => 'PORTRAIT_NOT_ALLOWED_ERROR',
        self::CORRUPTED_IMAGE_ERROR => 'CORRUPTED_IMAGE_ERROR',
    ];

    public array|string $mimeTypes = 'image/*';
    public ?int $minWidth = null;
    public ?int $maxWidth = null;
    public ?int $maxHeight = null;
    public ?int $minHeight = null;
    public int|float|null $maxRatio = null;
    public int|float|null $minRatio = null;
    public int|float|null $minPixels = null;
    public int|float|null $maxPixels = null;
    public bool $allowSquare = true;
    public bool $allowLandscape = true;
    public bool $allowPortrait = true;
    public bool $detectCorrupted = false;

    // The constant for a wrong MIME type is taken from the parent class.
    public string $mimeTypesMessage = 'This file is not a valid image.';
    public string $sizeNotDetectedMessage = 'The size of the image could not be detected.';
    public string $maxWidthMessage = 'The image width is too big ({{ width }}px). Allowed maximum width is {{ max_width }}px.';
    public string $minWidthMessage = 'The image width is too small ({{ width }}px). Minimum width expected is {{ min_width }}px.';
    public string $maxHeightMessage = 'The image height is too big ({{ height }}px). Allowed maximum height is {{ max_height }}px.';
    public string $minHeightMessage = 'The image height is too small ({{ height }}px). Minimum height expected is {{ min_height }}px.';
    public string $minPixelsMessage = 'The image has too few pixels ({{ pixels }} pixels). Minimum amount expected is {{ min_pixels }} pixels.';
    public string $maxPixelsMessage = 'The image has too many pixels ({{ pixels }} pixels). Maximum amount expected is {{ max_pixels }} pixels.';
    public string $maxRatioMessage = 'The image ratio is too big ({{ ratio }}). Allowed maximum ratio is {{ max_ratio }}.';
    public string $minRatioMessage = 'The image ratio is too small ({{ ratio }}). Minimum ratio expected is {{ min_ratio }}.';
    public string $allowSquareMessage = 'The image is square ({{ width }}x{{ height }}px). Square images are not allowed.';
    public string $allowLandscapeMessage = 'The image is landscape oriented ({{ width }}x{{ height }}px). Landscape oriented images are not allowed.';
    public string $allowPortraitMessage = 'The image is portrait oriented ({{ width }}x{{ height }}px). Portrait oriented images are not allowed.';
    public string $corruptedMessage = 'The image file is corrupted.';

    public function __construct(
        array $options = null,
        int|string $maxSize = null,
        bool $binaryFormat = null,
        array $mimeTypes = null,
        int $filenameMaxLength = null,
        int $minWidth = null,
        int $maxWidth = null,
        int $maxHeight = null,
        int $minHeight = null,
        int|float $maxRatio = null,
        int|float $minRatio = null,
        int|float $minPixels = null,
        int|float $maxPixels = null,
        bool $allowSquare = null,
        bool $allowLandscape = null,
        bool $allowPortrait = null,
        bool $detectCorrupted = null,
        string $notFoundMessage = null,
        string $notReadableMessage = null,
        string $maxSizeMessage = null,
        string $mimeTypesMessage = null,
        string $disallowEmptyMessage = null,
        string $filenameTooLongMessage = null,
        string $uploadIniSizeErrorMessage = null,
        string $uploadFormSizeErrorMessage = null,
        string $uploadPartialErrorMessage = null,
        string $uploadNoFileErrorMessage = null,
        string $uploadNoTmpDirErrorMessage = null,
        string $uploadCantWriteErrorMessage = null,
        string $uploadExtensionErrorMessage = null,
        string $uploadErrorMessage = null,
        string $sizeNotDetectedMessage = null,
        string $maxWidthMessage = null,
        string $minWidthMessage = null,
        string $maxHeightMessage = null,
        string $minHeightMessage = null,
        string $minPixelsMessage = null,
        string $maxPixelsMessage = null,
        string $maxRatioMessage = null,
        string $minRatioMessage = null,
        string $allowSquareMessage = null,
        string $allowLandscapeMessage = null,
        string $allowPortraitMessage = null,
        string $corruptedMessage = null,
        array $groups = null,
        mixed $payload = null
    ) {
        parent::__construct(
            $options,
            $maxSize,
            $binaryFormat,
            $mimeTypes,
            $filenameMaxLength,
            $notFoundMessage,
            $notReadableMessage,
            $maxSizeMessage,
            $mimeTypesMessage,
            $disallowEmptyMessage,
            $filenameTooLongMessage,
            $uploadIniSizeErrorMessage,
            $uploadFormSizeErrorMessage,
            $uploadPartialErrorMessage,
            $uploadNoFileErrorMessage,
            $uploadNoTmpDirErrorMessage,
            $uploadCantWriteErrorMessage,
            $uploadExtensionErrorMessage,
            $uploadErrorMessage,
            $groups,
            $payload
        );

        $this->minWidth = $minWidth ?? $this->minWidth;
        $this->maxWidth = $maxWidth ?? $this->maxWidth;
        $this->maxHeight = $maxHeight ?? $this->maxHeight;
        $this->minHeight = $minHeight ?? $this->minHeight;
        $this->maxRatio = $maxRatio ?? $this->maxRatio;
        $this->minRatio = $minRatio ?? $this->minRatio;
        $this->minPixels = $minPixels ?? $this->minPixels;
        $this->maxPixels = $maxPixels ?? $this->maxPixels;
        $this->allowSquare = $allowSquare ?? $this->allowSquare;
        $this->allowLandscape = $allowLandscape ?? $this->allowLandscape;
        $this->allowPortrait = $allowPortrait ?? $this->allowPortrait;
        $this->detectCorrupted = $detectCorrupted ?? $this->detectCorrupted;
        $this->sizeNotDetectedMessage = $sizeNotDetectedMessage ?? $this->sizeNotDetectedMessage;
        $this->maxWidthMessage = $maxWidthMessage ?? $this->maxWidthMessage;
        $this->minWidthMessage = $minWidthMessage ?? $this->minWidthMessage;
        $this->maxHeightMessage = $maxHeightMessage ?? $this->maxHeightMessage;
        $this->minHeightMessage = $minHeightMessage ?? $this->minHeightMessage;
        $this->minPixelsMessage = $minPixelsMessage ?? $this->minPixelsMessage;
        $this->maxPixelsMessage = $maxPixelsMessage ?? $this->maxPixelsMessage;
        $this->maxRatioMessage = $maxRatioMessage ?? $this->maxRatioMessage;
        $this->minRatioMessage = $minRatioMessage ?? $this->minRatioMessage;
        $this->allowSquareMessage = $allowSquareMessage ?? $this->allowSquareMessage;
        $this->allowLandscapeMessage = $allowLandscapeMessage ?? $this->allowLandscapeMessage;
        $this->allowPortraitMessage = $allowPortraitMessage ?? $this->allowPortraitMessage;
        $this->corruptedMessage = $corruptedMessage ?? $this->corruptedMessage;

        if (!\in_array('image/*', (array) $this->mimeTypes, true) && !\array_key_exists('mimeTypesMessage', $options ?? []) && null === $mimeTypesMessage) {
            $this->mimeTypesMessage = 'The mime type of the file is invalid ({{ type }}). Allowed mime types are {{ types }}.';
        }
    }
}
