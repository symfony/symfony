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

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
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
        self::FILENAME_TOO_LONG => 'FILENAME_TOO_LONG',
    ];

    /**
     * @deprecated since Symfony 6.1, use const ERROR_NAMES instead
     */
    protected static $errorNames = self::ERROR_NAMES;

    public $binaryFormat;
    public $mimeTypes = [];
    public ?int $filenameMaxLength = null;
    public array|string|null $extensions = [];
    public $notFoundMessage = 'The file could not be found.';
    public $notReadableMessage = 'The file is not readable.';
    public $maxSizeMessage = 'The file is too large ({{ size }} {{ suffix }}). Allowed maximum size is {{ limit }} {{ suffix }}.';
    public $mimeTypesMessage = 'The mime type of the file is invalid ({{ type }}). Allowed mime types are {{ types }}.';
    public string $extensionsMessage = 'The extension of the file is invalid ({{ extension }}). Allowed extensions are {{ extensions }}.';
    public $disallowEmptyMessage = 'An empty file is not allowed.';
    public $filenameTooLongMessage = 'The filename is too long. It should have {{ filename_max_length }} character or less.|The filename is too long. It should have {{ filename_max_length }} characters or less.';

    public $uploadIniSizeErrorMessage = 'The file is too large. Allowed maximum size is {{ limit }} {{ suffix }}.';
    public $uploadFormSizeErrorMessage = 'The file is too large.';
    public $uploadPartialErrorMessage = 'The file was only partially uploaded.';
    public $uploadNoFileErrorMessage = 'No file was uploaded.';
    public $uploadNoTmpDirErrorMessage = 'No temporary folder was configured in php.ini.';
    public $uploadCantWriteErrorMessage = 'Cannot write temporary file to disk.';
    public $uploadExtensionErrorMessage = 'A PHP extension caused the upload to fail.';
    public $uploadErrorMessage = 'The file could not be uploaded.';

    protected $maxSize;

    /**
     * @param array<string|string[]>|string $extensions
     */
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

    /**
     * @return void
     */
    public function __set(string $option, mixed $value)
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
            throw new ConstraintDefinitionException(sprintf('"%s" is not a valid maximum size.', $maxSize));
        }
    }
}
