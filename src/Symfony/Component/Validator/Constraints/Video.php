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

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Symfony\Component\Validator\Exception\LogicException;

/**
 * @author Kev <https://github.com/symfonyaml>
 * @author Nicolas Grekas <p@tchwork.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Video extends File
{
    public const SIZE_NOT_DETECTED_ERROR = '5dab98df-43c8-481b-94f9-46a3c958285c';
    public const TOO_WIDE_ERROR = '9e18d6a4-aeda-4644-be8e-9e29dbfd6c4a';
    public const TOO_NARROW_ERROR = 'b267f54b-d994-46d4-9ca6-338fc4f7962f';
    public const TOO_HIGH_ERROR = '44f4c411-0199-48c2-b597-df1f5944ccde';
    public const TOO_LOW_ERROR = '0b6bc3ce-df90-40f9-90aa-5bbb840cb481';
    public const TOO_FEW_PIXEL_ERROR = '510ddf98-2eda-436e-be7e-b6f107bc0e22';
    public const TOO_MANY_PIXEL_ERROR = 'ff0a8ee8-951d-4c97-afe2-03c0d61a2a02';
    public const RATIO_TOO_BIG_ERROR = '5e6b9c21-d4d8-444d-9f4c-e3ff1e25a9a6';
    public const RATIO_TOO_SMALL_ERROR = '26985857-7447-49dc-b271-1477a76cc63c';
    public const SQUARE_NOT_ALLOWED_ERROR = '18500335-b868-4056-b2a2-aa2aeeb0cbdf';
    public const LANDSCAPE_NOT_ALLOWED_ERROR = 'cbf38fbc-04c0-457a-8c29-a6f3080e415a';
    public const PORTRAIT_NOT_ALLOWED_ERROR = '6c3e34a8-94d5-4434-9f20-fb9c0f3ab531';
    public const CORRUPTED_VIDEO_ERROR = '591b9c4d-d357-425f-8672-6b187816550e';
    public const MULTIPLE_VIDEO_STREAMS_ERROR = '2d1b2b2e-3f37-4fdd-9a2a-8b6b77b2a6a3';
    public const UNSUPPORTED_VIDEO_CODEC_ERROR = 'a9f2f6f7-2b5a-4f3c-b746-d3e2e9d1b2a1';
    public const UNSUPPORTED_VIDEO_CONTAINER_ERROR = 'b7c9d2a4-5e1f-4aa0-8f9d-1c3e2b4a6d7e';

    // Include the mapping from the base class

    protected const ERROR_NAMES = [
        self::NOT_FOUND_ERROR => 'NOT_FOUND_ERROR',
        self::NOT_READABLE_ERROR => 'NOT_READABLE_ERROR',
        self::EMPTY_ERROR => 'EMPTY_ERROR',
        self::TOO_LARGE_ERROR => 'TOO_LARGE_ERROR',
        self::INVALID_MIME_TYPE_ERROR => 'INVALID_MIME_TYPE_ERROR',
        self::FILENAME_TOO_LONG => 'FILENAME_TOO_LONG',
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
        self::CORRUPTED_VIDEO_ERROR => 'CORRUPTED_VIDEO_ERROR',
        self::MULTIPLE_VIDEO_STREAMS_ERROR => 'MULTIPLE_VIDEO_STREAMS_ERROR',
        self::UNSUPPORTED_VIDEO_CODEC_ERROR => 'UNSUPPORTED_VIDEO_CODEC_ERROR',
        self::UNSUPPORTED_VIDEO_CONTAINER_ERROR => 'UNSUPPORTED_VIDEO_CONTAINER_ERROR',
    ];

    public array|string $mimeTypes = 'video/*';
    public ?int $minWidth = null;
    public ?int $maxWidth = null;
    public ?int $maxHeight = null;
    public ?int $minHeight = null;
    public int|float|null $maxRatio = null;
    public int|float|null $minRatio = null;
    public int|float|null $minPixels = null;
    public int|float|null $maxPixels = null;
    public ?bool $allowSquare = true;
    public ?bool $allowLandscape = true;
    public ?bool $allowPortrait = true;
    public array $allowedCodecs = ['h264', 'hevc', 'h265', 'vp9', 'av1', 'mpeg4', 'mpeg2video'];
    public array $allowedContainers = ['mp4', 'mov', 'mkv', 'webm', 'avi'];

    // The constant for a wrong MIME type is taken from the parent class.
    public string $mimeTypesMessage = 'This file is not a valid video.';
    public string $sizeNotDetectedMessage = 'The size of the video could not be detected.';
    public string $maxWidthMessage = 'The video width is too big ({{ width }}px). Allowed maximum width is {{ max_width }}px.';
    public string $minWidthMessage = 'The video width is too small ({{ width }}px). Minimum width expected is {{ min_width }}px.';
    public string $maxHeightMessage = 'The video height is too big ({{ height }}px). Allowed maximum height is {{ max_height }}px.';
    public string $minHeightMessage = 'The video height is too small ({{ height }}px). Minimum height expected is {{ min_height }}px.';
    public string $minPixelsMessage = 'The video has too few pixels ({{ pixels }} pixels). Minimum amount expected is {{ min_pixels }} pixels.';
    public string $maxPixelsMessage = 'The video has too many pixels ({{ pixels }} pixels). Maximum amount expected is {{ max_pixels }} pixels.';
    public string $maxRatioMessage = 'The video ratio is too big ({{ ratio }}). Allowed maximum ratio is {{ max_ratio }}.';
    public string $minRatioMessage = 'The video ratio is too small ({{ ratio }}). Minimum ratio expected is {{ min_ratio }}.';
    public string $allowSquareMessage = 'The video is square ({{ width }}x{{ height }}px). Square videos are not allowed.';
    public string $allowLandscapeMessage = 'The video is landscape oriented ({{ width }}x{{ height }}px). Landscape oriented videos are not allowed.';
    public string $allowPortraitMessage = 'The video is portrait oriented ({{ width }}x{{ height }}px). Portrait oriented videos are not allowed.';
    public string $corruptedMessage = 'The video file is corrupted.';
    public string $multipleVideoStreamsMessage = 'The video contains multiple streams. Only one stream is allowed.';
    public string $unsupportedCodecMessage = 'Unsupported video codec "{{ codec }}".';
    public string $unsupportedContainerMessage = 'Unsupported video container "{{ container }}".';

    /**
     * @param positive-int|string|null $maxSize                     The max size of the underlying file
     * @param bool|null                $binaryFormat                Pass true to use binary-prefixed units (KiB, MiB, etc.) or false to use SI-prefixed units (kB, MB) in displayed messages. Pass null to guess the format from the maxSize option. (defaults to null)
     * @param non-empty-string[]|null  $mimeTypes                   Acceptable media types
     * @param positive-int|null        $filenameMaxLength           Maximum length of the file name
     * @param string|null              $disallowEmptyMessage        Enable empty upload validation with this message in case of error
     * @param string|null              $uploadIniSizeErrorMessage   Message if the file size exceeds the max size configured in php.ini
     * @param string|null              $uploadFormSizeErrorMessage  Message if the file size exceeds the max size configured in the HTML input field
     * @param string|null              $uploadPartialErrorMessage   Message if the file is only partially uploaded
     * @param string|null              $uploadNoTmpDirErrorMessage  Message if there is no upload_tmp_dir in php.ini
     * @param string|null              $uploadCantWriteErrorMessage Message if the uploaded file can not be stored in the temporary directory
     * @param string|null              $uploadErrorMessage          Message if an unknown error occurred on upload
     * @param string[]|null            $groups
     * @param int<0, int>|null         $minWidth                    Minimum video width
     * @param positive-int|null        $maxWidth                    Maximum video width
     * @param positive-int|null        $maxHeight                   Maximum video height
     * @param int<0, int>|null         $minHeight                   Minimum video weight
     * @param positive-int|float|null  $maxRatio                    Maximum video ratio
     * @param int<0, max>|float|null   $minRatio                    Minimum video ratio
     * @param int<0, max>|float|null   $minPixels                   Minimum amount of pixels
     * @param positive-int|float|null  $maxPixels                   Maximum amount of pixels
     * @param bool|null                $allowSquare                 Whether to allow a square video (defaults to true)
     * @param bool|null                $allowLandscape              Whether to allow a landscape video (defaults to true)
     * @param bool|null                $allowPortrait               Whether to allow a portrait video (defaults to true)
     * @param string|null              $sizeNotDetectedMessage      Message if the system can not determine video size and there is a size constraint to validate
     * @param string[]|null            $allowedCodecs               Allowed codec names
     * @param string[]|null            $allowedContainers           Allowed container names
     *
     * @see https://www.iana.org/assignments/media-types/media-types.xhtml Existing media types
     */
    public function __construct(
        int|string|null $maxSize = null,
        ?bool $binaryFormat = null,
        array|string|null $mimeTypes = null,
        ?int $filenameMaxLength = null,
        ?int $minWidth = null,
        ?int $maxWidth = null,
        ?int $maxHeight = null,
        ?int $minHeight = null,
        int|float|null $maxRatio = null,
        int|float|null $minRatio = null,
        int|float|null $minPixels = null,
        int|float|null $maxPixels = null,
        ?bool $allowSquare = null,
        ?bool $allowLandscape = null,
        ?bool $allowPortrait = null,
        ?array $allowedCodecs = null,
        ?array $allowedContainers = null,
        ?string $notFoundMessage = null,
        ?string $notReadableMessage = null,
        ?string $maxSizeMessage = null,
        ?string $mimeTypesMessage = null,
        ?string $disallowEmptyMessage = null,
        ?string $filenameTooLongMessage = null,
        ?string $uploadIniSizeErrorMessage = null,
        ?string $uploadFormSizeErrorMessage = null,
        ?string $uploadPartialErrorMessage = null,
        ?string $uploadNoFileErrorMessage = null,
        ?string $uploadNoTmpDirErrorMessage = null,
        ?string $uploadCantWriteErrorMessage = null,
        ?string $uploadExtensionErrorMessage = null,
        ?string $uploadErrorMessage = null,
        ?string $sizeNotDetectedMessage = null,
        ?string $maxWidthMessage = null,
        ?string $minWidthMessage = null,
        ?string $maxHeightMessage = null,
        ?string $minHeightMessage = null,
        ?string $minPixelsMessage = null,
        ?string $maxPixelsMessage = null,
        ?string $maxRatioMessage = null,
        ?string $minRatioMessage = null,
        ?string $allowSquareMessage = null,
        ?string $allowLandscapeMessage = null,
        ?string $allowPortraitMessage = null,
        ?string $corruptedMessage = null,
        ?string $multipleVideoStreamsMessage = null,
        ?string $unsupportedCodecMessage = null,
        ?string $unsupportedContainerMessage = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        static $hasFfprobe;
        if (!$hasFfprobe) {
            if (!class_exists(Process::class)) {
                throw new LogicException('The Process component is required to use the Video constraint. Try running "composer require symfony/process".');
            }
            if (!$hasFfprobe ??= (new ExecutableFinder())->find('ffprobe')) {
                throw new LogicException('The ffprobe binary is required to use the Video constraint.');
            }
        }

        parent::__construct(
            null,
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
        $this->allowedCodecs = $allowedCodecs ?? $this->allowedCodecs;
        $this->allowedContainers = $allowedContainers ?? $this->allowedContainers;
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
        $this->multipleVideoStreamsMessage = $multipleVideoStreamsMessage ?? $this->multipleVideoStreamsMessage;
        $this->unsupportedCodecMessage = $unsupportedCodecMessage ?? $this->unsupportedCodecMessage;
        $this->unsupportedContainerMessage = $unsupportedContainerMessage ?? $this->unsupportedContainerMessage;

        if (!\in_array('video/*', (array) $this->mimeTypes, true) && null === $mimeTypesMessage) {
            $this->mimeTypesMessage = 'The mime type of the file is invalid ({{ type }}). Allowed mime types are {{ types }}.';
        }
    }
}
