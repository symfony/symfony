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

use Symfony\Component\Validator\Attribute\HasNamedArguments;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

/**
 * Validates that a value is a valid "file".
 *
 * A file can be one of the following:
 *   - A string (or object with a __toString() method) path to an existing file;
 *   - A valid {@see \Symfony\Component\HttpFoundation\File\File File} object (including objects of {@see \Symfony\Component\HttpFoundation\File\UploadedFile UploadedFile} class).
 *
 * @property int $maxSize
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class File extends Constraint
{
    // Check the Image constraint for clashes if adding new constants here

    public const NOT_FOUND_ERROR = 'd2a3fb6e-7ddc-4210-8fbf-2ab345ce1998';
    public const NOT_READABLE_ERROR = 'c20c92a4-5bfa-4202-9477-28e800e0f6ff';
    public const EMPTY_ERROR = '5d743385-9775-4aa5-8ff5-495fb1e60137';
    public const TOO_LARGE_ERROR = 'df8637af-d466-48c6-a59d-e7126250a654';
    public const INVALID_MIME_TYPE_ERROR = '744f00bc-4389-4c74-92de-9a43cde55534';
    public const INVALID_EXTENSION_ERROR = 'c8c7315c-6186-4719-8b71-5659e16bdcb7';
    public const FILENAME_TOO_LONG = 'e5706483-91a8-49d8-9a59-5e81a3c634a8';

    protected const ERROR_NAMES = [
        self::NOT_FOUND_ERROR => 'NOT_FOUND_ERROR',
        self::NOT_READABLE_ERROR => 'NOT_READABLE_ERROR',
        self::EMPTY_ERROR => 'EMPTY_ERROR',
        self::TOO_LARGE_ERROR => 'TOO_LARGE_ERROR',
        self::INVALID_MIME_TYPE_ERROR => 'INVALID_MIME_TYPE_ERROR',
        self::INVALID_EXTENSION_ERROR => 'INVALID_EXTENSION_ERROR',
        self::FILENAME_TOO_LONG => 'FILENAME_TOO_LONG',
    ];

    public ?bool $binaryFormat = null;
    public array|string $mimeTypes = [];
    public ?int $filenameMaxLength = null;
    public array|string $extensions = [];
    public string $notFoundMessage = 'The file could not be found.';
    public string $notReadableMessage = 'The file is not readable.';
    public string $maxSizeMessage = 'The file is too large ({{ size }} {{ suffix }}). Allowed maximum size is {{ limit }} {{ suffix }}.';
    public string $mimeTypesMessage = 'The mime type of the file is invalid ({{ type }}). Allowed mime types are {{ types }}.';
    public string $extensionsMessage = 'The extension of the file is invalid ({{ extension }}). Allowed extensions are {{ extensions }}.';
    public string $disallowEmptyMessage = 'An empty file is not allowed.';
    public string $filenameTooLongMessage = 'The filename is too long. It should have {{ filename_max_length }} character or less.|The filename is too long. It should have {{ filename_max_length }} characters or less.';

    public string $uploadIniSizeErrorMessage = 'The file is too large. Allowed maximum size is {{ limit }} {{ suffix }}.';
    public string $uploadFormSizeErrorMessage = 'The file is too large.';
    public string $uploadPartialErrorMessage = 'The file was only partially uploaded.';
    public string $uploadNoFileErrorMessage = 'No file was uploaded.';
    public string $uploadNoTmpDirErrorMessage = 'No temporary folder was configured in php.ini.';
    public string $uploadCantWriteErrorMessage = 'Cannot write temporary file to disk.';
    public string $uploadExtensionErrorMessage = 'A PHP extension caused the upload to fail.';
    public string $uploadErrorMessage = 'The file could not be uploaded.';

    protected int|string|null $maxSize = null;

    /**
     * @param array<string,mixed>|null           $options
     * @param positive-int|string|null           $maxSize                     The max size of the underlying file
     * @param bool|null                          $binaryFormat                Pass true to use binary-prefixed units (KiB, MiB, etc.) or false to use SI-prefixed units (kB, MB) in displayed messages. Pass null to guess the format from the maxSize option. (defaults to null)
     * @param string[]|string|null               $mimeTypes                   Acceptable media type(s). Prefer the extensions option that also enforce the file's extension consistency.
     * @param positive-int|null                  $filenameMaxLength           Maximum length of the file name
     * @param string|null                        $disallowEmptyMessage        Enable empty upload validation with this message in case of error
     * @param string|null                        $uploadIniSizeErrorMessage   Message if the file size exceeds the max size configured in php.ini
     * @param string|null                        $uploadFormSizeErrorMessage  Message if the file size exceeds the max size configured in the HTML input field
     * @param string|null                        $uploadPartialErrorMessage   Message if the file is only partially uploaded
     * @param string|null                        $uploadNoTmpDirErrorMessage  Message if there is no upload_tmp_dir in php.ini
     * @param string|null                        $uploadCantWriteErrorMessage Message if the uploaded file can not be stored in the temporary directory
     * @param string|null                        $uploadErrorMessage          Message if an unknown error occurred on upload
     * @param string[]|null                      $groups
     * @param array<string|string[]>|string|null $extensions                  A list of valid extensions to check. Related media types are also enforced ({@see https://symfony.com/doc/current/reference/constraints/File.html#extensions})
     *
     * @see https://www.iana.org/assignments/media-types/media-types.xhtml Existing media types
     */
    #[HasNamedArguments]
    public function __construct(
        ?array $options = null,
        int|string|null $maxSize = null,
        ?bool $binaryFormat = null,
        array|string|null $mimeTypes = null,
        ?int $filenameMaxLength = null,
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
        ?array $groups = null,
        mixed $payload = null,

        array|string|null $extensions = null,
        ?string $extensionsMessage = null,
    ) {
        if ($options) {
            trigger_deprecation('symfony/validator', '7.2', 'Passing an array of options to configure the "%s" constraint is deprecated, use named arguments instead.', static::class);
        }

        parent::__construct($options, $groups, $payload);

        $this->maxSize = $maxSize ?? $this->maxSize;
        $this->binaryFormat = $binaryFormat ?? $this->binaryFormat;
        $this->mimeTypes = $mimeTypes ?? $this->mimeTypes;
        $this->filenameMaxLength = $filenameMaxLength ?? $this->filenameMaxLength;
        $this->extensions = $extensions ?? $this->extensions;
        $this->notFoundMessage = $notFoundMessage ?? $this->notFoundMessage;
        $this->notReadableMessage = $notReadableMessage ?? $this->notReadableMessage;
        $this->maxSizeMessage = $maxSizeMessage ?? $this->maxSizeMessage;
        $this->mimeTypesMessage = $mimeTypesMessage ?? $this->mimeTypesMessage;
        $this->extensionsMessage = $extensionsMessage ?? $this->extensionsMessage;
        $this->disallowEmptyMessage = $disallowEmptyMessage ?? $this->disallowEmptyMessage;
        $this->filenameTooLongMessage = $filenameTooLongMessage ?? $this->filenameTooLongMessage;
        $this->uploadIniSizeErrorMessage = $uploadIniSizeErrorMessage ?? $this->uploadIniSizeErrorMessage;
        $this->uploadFormSizeErrorMessage = $uploadFormSizeErrorMessage ?? $this->uploadFormSizeErrorMessage;
        $this->uploadPartialErrorMessage = $uploadPartialErrorMessage ?? $this->uploadPartialErrorMessage;
        $this->uploadNoFileErrorMessage = $uploadNoFileErrorMessage ?? $this->uploadNoFileErrorMessage;
        $this->uploadNoTmpDirErrorMessage = $uploadNoTmpDirErrorMessage ?? $this->uploadNoTmpDirErrorMessage;
        $this->uploadCantWriteErrorMessage = $uploadCantWriteErrorMessage ?? $this->uploadCantWriteErrorMessage;
        $this->uploadExtensionErrorMessage = $uploadExtensionErrorMessage ?? $this->uploadExtensionErrorMessage;
        $this->uploadErrorMessage = $uploadErrorMessage ?? $this->uploadErrorMessage;

        if (null !== $this->maxSize) {
            $this->normalizeBinaryFormat($this->maxSize);
        }
    }

    public function __set(string $option, mixed $value): void
    {
        if ('maxSize' === $option) {
            $this->normalizeBinaryFormat($value);

            return;
        }

        parent::__set($option, $value);
    }

    public function __get(string $option): mixed
    {
        if ('maxSize' === $option) {
            return $this->maxSize;
        }

        return parent::__get($option);
    }

    public function __isset(string $option): bool
    {
        if ('maxSize' === $option) {
            return true;
        }

        return parent::__isset($option);
    }

    private function normalizeBinaryFormat(int|string $maxSize): void
    {
        $factors = [
            'k' => 1000,
            'ki' => 1 << 10,
            'm' => 1000 * 1000,
            'mi' => 1 << 20,
            'g' => 1000 * 1000 * 1000,
            'gi' => 1 << 30,
        ];
        if (ctype_digit((string) $maxSize)) {
            $this->maxSize = (int) $maxSize;
            $this->binaryFormat ??= false;
        } elseif (preg_match('/^(\d++)('.implode('|', array_keys($factors)).')$/i', $maxSize, $matches)) {
            $this->maxSize = $matches[1] * $factors[$unit = strtolower($matches[2])];
            $this->binaryFormat ??= 2 === \strlen($unit);
        } else {
            throw new ConstraintDefinitionException(\sprintf('"%s" is not a valid maximum size.', $maxSize));
        }
    }
}
