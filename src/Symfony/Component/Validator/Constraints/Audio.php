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
 * @author Valmont PEHAUT-PIETRI <https://github.com/Valmonzo>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class Audio extends File
{
    public const TOO_SHORT_ERROR = '11e7e70a-6089-45e6-8eb8-3b2f4792b1d3';
    public const TOO_LONG_ERROR = '97dc38c7-5c32-45b8-9b23-fe5fe1aee198';
    public const DURATION_NOT_DETECTED_ERROR = '9a8ef5ad-0c69-4910-8766-28616cc028a5';
    public const BITRATE_TOO_LOW_ERROR = '643a4c5e-10b7-45e5-ab48-38eacc963652';
    public const BITRATE_TOO_HIGH_ERROR = 'cfcc037f-a511-4da3-afe2-3e9ed3057b44';
    public const BITRATE_NOT_DETECTED_ERROR = '90b7db23-8066-492c-b504-2d386f3a9df0';
    public const UNSUPPORTED_SAMPLE_RATE_ERROR = '746687e3-637f-4296-adf9-9f248eca8136';
    public const SAMPLE_RATE_NOT_DETECTED_ERROR = '9fe91334-6582-4f95-a81b-58ddbaf745b4';
    public const TOO_FEW_CHANNELS_ERROR = '784c376d-20ee-4971-b56c-aea47042ee73';
    public const TOO_MANY_CHANNELS_ERROR = 'cf035c98-345e-42d4-b04b-86af800eb468';
    public const CHANNELS_NOT_DETECTED_ERROR = 'c26884dc-5df4-4d19-b287-9804ddb05f2a';
    public const CORRUPTED_AUDIO_ERROR = 'e68a0630-9e50-4939-ae76-21b59b9a05ed';
    public const NO_AUDIO_STREAM_ERROR = '3cef56df-a50c-4594-a342-3a4132ae1864';
    public const MULTIPLE_AUDIO_STREAMS_ERROR = '296d9a45-2aac-4e3f-bf67-f81881f6f647';
    public const UNSUPPORTED_AUDIO_CODEC_ERROR = '96cd7ea0-7556-4477-a510-62747e2feabd';
    public const UNSUPPORTED_AUDIO_CONTAINER_ERROR = 'b6fd945f-4484-4cb0-9c8e-d4dbaa1dfec3';

    protected const ERROR_NAMES = [
        // Include the mapping from the base class
        self::NOT_FOUND_ERROR => 'NOT_FOUND_ERROR',
        self::NOT_READABLE_ERROR => 'NOT_READABLE_ERROR',
        self::EMPTY_ERROR => 'EMPTY_ERROR',
        self::TOO_LARGE_ERROR => 'TOO_LARGE_ERROR',
        self::INVALID_MIME_TYPE_ERROR => 'INVALID_MIME_TYPE_ERROR',
        self::FILENAME_TOO_LONG => 'FILENAME_TOO_LONG',
        // Mapping for Audio validation errors
        self::TOO_SHORT_ERROR => 'TOO_SHORT_ERROR',
        self::TOO_LONG_ERROR => 'TOO_LONG_ERROR',
        self::DURATION_NOT_DETECTED_ERROR => 'DURATION_NOT_DETECTED_ERROR',
        self::BITRATE_TOO_LOW_ERROR => 'BITRATE_TOO_LOW_ERROR',
        self::BITRATE_TOO_HIGH_ERROR => 'BITRATE_TOO_HIGH_ERROR',
        self::BITRATE_NOT_DETECTED_ERROR => 'BITRATE_NOT_DETECTED_ERROR',
        self::UNSUPPORTED_SAMPLE_RATE_ERROR => 'UNSUPPORTED_SAMPLE_RATE_ERROR',
        self::SAMPLE_RATE_NOT_DETECTED_ERROR => 'SAMPLE_RATE_NOT_DETECTED_ERROR',
        self::TOO_FEW_CHANNELS_ERROR => 'TOO_FEW_CHANNELS_ERROR',
        self::TOO_MANY_CHANNELS_ERROR => 'TOO_MANY_CHANNELS_ERROR',
        self::CHANNELS_NOT_DETECTED_ERROR => 'CHANNELS_NOT_DETECTED_ERROR',
        self::CORRUPTED_AUDIO_ERROR => 'CORRUPTED_AUDIO_ERROR',
        self::NO_AUDIO_STREAM_ERROR => 'NO_AUDIO_STREAM_ERROR',
        self::MULTIPLE_AUDIO_STREAMS_ERROR => 'MULTIPLE_AUDIO_STREAMS_ERROR',
        self::UNSUPPORTED_AUDIO_CODEC_ERROR => 'UNSUPPORTED_AUDIO_CODEC_ERROR',
        self::UNSUPPORTED_AUDIO_CONTAINER_ERROR => 'UNSUPPORTED_AUDIO_CONTAINER_ERROR',
    ];

    public array|string $mimeTypes = 'audio/*';
    public int|float|null $minDuration = null;
    public int|float|null $maxDuration = null;
    public ?int $minBitrate = null;
    public ?int $maxBitrate = null;
    public array $allowedSampleRates = [];
    public ?int $minChannels = null;
    public ?int $maxChannels = null;
    public array $allowedCodecs = [];
    public array $allowedContainers = [];

    // The constant for a wrong MIME type is taken from the parent class.
    public string $mimeTypesMessage = 'This file is not a valid audio file.';
    public string $minDurationMessage = 'The audio is too short ({{ duration }} seconds). Minimum duration expected is {{ min_duration }} seconds.';
    public string $maxDurationMessage = 'The audio is too long ({{ duration }} seconds). Allowed maximum duration is {{ max_duration }} seconds.';
    public string $durationNotDetectedMessage = 'The duration of the audio could not be detected.';
    public string $minBitrateMessage = 'The audio bitrate is too low ({{ bitrate }} bps). Minimum bitrate expected is {{ min_bitrate }} bps.';
    public string $maxBitrateMessage = 'The audio bitrate is too high ({{ bitrate }} bps). Allowed maximum bitrate is {{ max_bitrate }} bps.';
    public string $bitrateNotDetectedMessage = 'The bitrate of the audio could not be detected.';
    public string $unsupportedSampleRateMessage = 'Unsupported audio sample rate ({{ sample_rate }} Hz).';
    public string $sampleRateNotDetectedMessage = 'The sample rate of the audio could not be detected.';
    public string $minChannelsMessage = 'The audio has too few channels ({{ channels }}). Minimum amount expected is {{ min_channels }}.';
    public string $maxChannelsMessage = 'The audio has too many channels ({{ channels }}). Maximum amount expected is {{ max_channels }}.';
    public string $channelsNotDetectedMessage = 'The number of audio channels could not be detected.';
    public string $corruptedMessage = 'The audio file is corrupted.';
    public string $noAudioStreamMessage = 'The file does not contain any audio stream.';
    public string $multipleAudioStreamsMessage = 'The audio contains multiple streams. Only one stream is allowed.';
    public string $unsupportedCodecMessage = 'Unsupported audio codec "{{ codec }}".';
    public string $unsupportedContainerMessage = 'Unsupported audio container "{{ container }}".';

    /**
     * @param positive-int|string|null $maxSize                      The max size of the underlying file
     * @param bool|null                $binaryFormat                 Pass true to use binary-prefixed units (KiB, MiB, etc.) or false to use SI-prefixed units (kB, MB) in displayed messages. Pass null to guess the format from the maxSize option. (defaults to null)
     * @param non-empty-string[]|null  $mimeTypes                    Acceptable media types
     * @param positive-int|null        $filenameMaxLength            Maximum length of the file name
     * @param int<0, max>|float|null   $minDuration                  Minimum audio duration in seconds
     * @param positive-int|float|null  $maxDuration                  Maximum audio duration in seconds
     * @param int<0, max>|null         $minBitrate                   Minimum audio bitrate in bits per second
     * @param positive-int|null        $maxBitrate                   Maximum audio bitrate in bits per second
     * @param positive-int[]|null      $allowedSampleRates           Allowed sample rates in hertz
     * @param int<0, max>|null         $minChannels                  Minimum number of audio channels
     * @param positive-int|null        $maxChannels                  Maximum number of audio channels
     * @param string[]|null            $allowedCodecs                Allowed codec names
     * @param string[]|null            $allowedContainers            Allowed container names
     * @param string|null              $disallowEmptyMessage         Enable empty upload validation with this message in case of error
     * @param string|null              $uploadIniSizeErrorMessage    Message if the file size exceeds the max size configured in php.ini
     * @param string|null              $uploadFormSizeErrorMessage   Message if the file size exceeds the max size configured in the HTML input field
     * @param string|null              $uploadPartialErrorMessage    Message if the file is only partially uploaded
     * @param string|null              $uploadNoTmpDirErrorMessage   Message if there is no upload_tmp_dir in php.ini
     * @param string|null              $uploadCantWriteErrorMessage  Message if the uploaded file can not be stored in the temporary directory
     * @param string|null              $uploadErrorMessage           Message if an unknown error occurred on upload
     * @param string|null              $durationNotDetectedMessage   Message if the system can not determine the audio duration and there is a duration constraint to validate
     * @param string|null              $bitrateNotDetectedMessage    Message if the system can not determine the audio bitrate and there is a bitrate constraint to validate
     * @param string|null              $sampleRateNotDetectedMessage Message if the system can not determine the audio sample rate and there is a sample rate constraint to validate
     * @param string|null              $channelsNotDetectedMessage   Message if the system can not determine the number of audio channels and there is a channel constraint to validate
     * @param string[]|null            $groups
     *
     * @see https://www.iana.org/assignments/media-types/media-types.xhtml Existing media types
     */
    public function __construct(
        int|string|null $maxSize = null,
        ?bool $binaryFormat = null,
        array|string|null $mimeTypes = null,
        ?int $filenameMaxLength = null,
        int|float|null $minDuration = null,
        int|float|null $maxDuration = null,
        ?int $minBitrate = null,
        ?int $maxBitrate = null,
        ?array $allowedSampleRates = null,
        ?int $minChannels = null,
        ?int $maxChannels = null,
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
        ?string $minDurationMessage = null,
        ?string $maxDurationMessage = null,
        ?string $durationNotDetectedMessage = null,
        ?string $minBitrateMessage = null,
        ?string $maxBitrateMessage = null,
        ?string $bitrateNotDetectedMessage = null,
        ?string $unsupportedSampleRateMessage = null,
        ?string $sampleRateNotDetectedMessage = null,
        ?string $minChannelsMessage = null,
        ?string $maxChannelsMessage = null,
        ?string $channelsNotDetectedMessage = null,
        ?string $corruptedMessage = null,
        ?string $noAudioStreamMessage = null,
        ?string $multipleAudioStreamsMessage = null,
        ?string $unsupportedCodecMessage = null,
        ?string $unsupportedContainerMessage = null,
        ?array $groups = null,
        mixed $payload = null,
        array|string|null $extensions = null,
        ?string $extensionsMessage = null,
        ?string $filenameCharset = null,
        ?string $filenameCountUnit = null,
        ?string $filenameCharsetMessage = null,
    ) {
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
            $payload,
            $extensions,
            $extensionsMessage,
            $filenameCharset,
            $filenameCountUnit,
            $filenameCharsetMessage,
        );

        $this->minDuration = $minDuration ?? $this->minDuration;
        $this->maxDuration = $maxDuration ?? $this->maxDuration;
        $this->minBitrate = $minBitrate ?? $this->minBitrate;
        $this->maxBitrate = $maxBitrate ?? $this->maxBitrate;
        $this->allowedSampleRates = $allowedSampleRates ?? $this->allowedSampleRates;
        $this->minChannels = $minChannels ?? $this->minChannels;
        $this->maxChannels = $maxChannels ?? $this->maxChannels;
        $this->allowedCodecs = $allowedCodecs ?? $this->allowedCodecs;
        $this->allowedContainers = $allowedContainers ?? $this->allowedContainers;
        $this->minDurationMessage = $minDurationMessage ?? $this->minDurationMessage;
        $this->maxDurationMessage = $maxDurationMessage ?? $this->maxDurationMessage;
        $this->durationNotDetectedMessage = $durationNotDetectedMessage ?? $this->durationNotDetectedMessage;
        $this->minBitrateMessage = $minBitrateMessage ?? $this->minBitrateMessage;
        $this->maxBitrateMessage = $maxBitrateMessage ?? $this->maxBitrateMessage;
        $this->bitrateNotDetectedMessage = $bitrateNotDetectedMessage ?? $this->bitrateNotDetectedMessage;
        $this->unsupportedSampleRateMessage = $unsupportedSampleRateMessage ?? $this->unsupportedSampleRateMessage;
        $this->sampleRateNotDetectedMessage = $sampleRateNotDetectedMessage ?? $this->sampleRateNotDetectedMessage;
        $this->minChannelsMessage = $minChannelsMessage ?? $this->minChannelsMessage;
        $this->maxChannelsMessage = $maxChannelsMessage ?? $this->maxChannelsMessage;
        $this->channelsNotDetectedMessage = $channelsNotDetectedMessage ?? $this->channelsNotDetectedMessage;
        $this->corruptedMessage = $corruptedMessage ?? $this->corruptedMessage;
        $this->noAudioStreamMessage = $noAudioStreamMessage ?? $this->noAudioStreamMessage;
        $this->multipleAudioStreamsMessage = $multipleAudioStreamsMessage ?? $this->multipleAudioStreamsMessage;
        $this->unsupportedCodecMessage = $unsupportedCodecMessage ?? $this->unsupportedCodecMessage;
        $this->unsupportedContainerMessage = $unsupportedContainerMessage ?? $this->unsupportedContainerMessage;

        if (!\in_array('audio/*', (array) $this->mimeTypes, true) && null === $mimeTypesMessage) {
            $this->mimeTypesMessage = 'The mime type of the file is invalid ({{ type }}). Allowed mime types are {{ types }}.';
        }
    }
}
