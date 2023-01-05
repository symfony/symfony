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

use Symfony\Component\HttpFoundation\File\File as FileObject;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\LogicException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * @author Bernhard Schussek <bschussek@gmail.com>
 */
class FileValidator extends ConstraintValidator
{
    public const KB_BYTES = 1000;
    public const MB_BYTES = 1000000;
    public const KIB_BYTES = 1024;
    public const MIB_BYTES = 1048576;

    private const SUFFICES = [
        1 => 'bytes',
        self::KB_BYTES => 'kB',
        self::MB_BYTES => 'MB',
        self::KIB_BYTES => 'KiB',
        self::MIB_BYTES => 'MiB',
    ];

    public function validate(mixed $value, Constraint $constraint)
    {
        if (!$constraint instanceof File) {
            throw new UnexpectedTypeException($constraint, File::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if ($value instanceof UploadedFile && !$value->isValid()) {
            switch ($value->getError()) {
                case \UPLOAD_ERR_INI_SIZE:
                    $iniLimitSize = UploadedFile::getMaxFilesize();
                    if ($constraint->maxSize && $constraint->maxSize < $iniLimitSize) {
                        $limitInBytes = $constraint->maxSize;
                        $binaryFormat = $constraint->binaryFormat;
                    } else {
                        $limitInBytes = $iniLimitSize;
                        $binaryFormat = $constraint->binaryFormat ?? true;
                    }

                    [, $limitAsString, $suffix] = $this->factorizeSizes(0, $limitInBytes, $binaryFormat);
                    $this->context->buildViolation($constraint->uploadIniSizeErrorMessage)
                        ->setParameter('{{ limit }}', $limitAsString)
                        ->setParameter('{{ suffix }}', $suffix)
                        ->setCode((string) \UPLOAD_ERR_INI_SIZE)
                        ->addViolation();

                    return;
                case \UPLOAD_ERR_FORM_SIZE:
                    $this->context->buildViolation($constraint->uploadFormSizeErrorMessage)
                        ->setCode((string) \UPLOAD_ERR_FORM_SIZE)
                        ->addViolation();

                    return;
                case \UPLOAD_ERR_PARTIAL:
                    $this->context->buildViolation($constraint->uploadPartialErrorMessage)
                        ->setCode((string) \UPLOAD_ERR_PARTIAL)
                        ->addViolation();

                    return;
                case \UPLOAD_ERR_NO_FILE:
                    $this->context->buildViolation($constraint->uploadNoFileErrorMessage)
                        ->setCode((string) \UPLOAD_ERR_NO_FILE)
                        ->addViolation();

                    return;
                case \UPLOAD_ERR_NO_TMP_DIR:
                    $this->context->buildViolation($constraint->uploadNoTmpDirErrorMessage)
                        ->setCode((string) \UPLOAD_ERR_NO_TMP_DIR)
                        ->addViolation();

                    return;
                case \UPLOAD_ERR_CANT_WRITE:
                    $this->context->buildViolation($constraint->uploadCantWriteErrorMessage)
                        ->setCode((string) \UPLOAD_ERR_CANT_WRITE)
                        ->addViolation();

                    return;
                case \UPLOAD_ERR_EXTENSION:
                    $this->context->buildViolation($constraint->uploadExtensionErrorMessage)
                        ->setCode((string) \UPLOAD_ERR_EXTENSION)
                        ->addViolation();

                    return;
                default:
                    $this->context->buildViolation($constraint->uploadErrorMessage)
                        ->setCode((string) $value->getError())
                        ->addViolation();

                    return;
            }
        }

        if (!\is_scalar($value) && !$value instanceof FileObject && !$value instanceof \Stringable) {
            throw new UnexpectedValueException($value, 'string');
        }

        $path = $value instanceof FileObject ? $value->getPathname() : (string) $value;

        if (!is_file($path)) {
            $this->context->buildViolation($constraint->notFoundMessage)
                ->setParameter('{{ file }}', $this->formatValue($path))
                ->setCode(File::NOT_FOUND_ERROR)
                ->addViolation();

            return;
        }

        if (!is_readable($path)) {
            $this->context->buildViolation($constraint->notReadableMessage)
                ->setParameter('{{ file }}', $this->formatValue($path))
                ->setCode(File::NOT_READABLE_ERROR)
                ->addViolation();

            return;
        }

        $sizeInBytes = filesize($path);
        $basename = $value instanceof UploadedFile ? $value->getClientOriginalName() : basename($path);

        if (0 === $sizeInBytes) {
            $this->context->buildViolation($constraint->disallowEmptyMessage)
                ->setParameter('{{ file }}', $this->formatValue($path))
                ->setParameter('{{ name }}', $this->formatValue($basename))
                ->setCode(File::EMPTY_ERROR)
                ->addViolation();

            return;
        }

        if ($constraint->maxSize) {
            $limitInBytes = $constraint->maxSize;

            if ($sizeInBytes > $limitInBytes) {
                [$sizeAsString, $limitAsString, $suffix] = $this->factorizeSizes($sizeInBytes, $limitInBytes, $constraint->binaryFormat);
                $this->context->buildViolation($constraint->maxSizeMessage)
                    ->setParameter('{{ file }}', $this->formatValue($path))
                    ->setParameter('{{ size }}', $sizeAsString)
                    ->setParameter('{{ limit }}', $limitAsString)
                    ->setParameter('{{ suffix }}', $suffix)
                    ->setParameter('{{ name }}', $this->formatValue($basename))
                    ->setCode(File::TOO_LARGE_ERROR)
                    ->addViolation();

                return;
            }
        }

        $mimeTypes = (array) $constraint->mimeTypes;

        if ($constraint->extensions) {
            $fileExtension = strtolower(pathinfo($basename, \PATHINFO_EXTENSION));

            $found = false;
            $normalizedExtensions = [];
            foreach ((array) $constraint->extensions as $k => $v) {
                if (!\is_string($k)) {
                    $k = $v;
                    $v = null;
                }

                $normalizedExtensions[] = $k;

                if ($fileExtension !== $k) {
                    continue;
                }

                $found = true;
                if (null === $v) {
                    if (!class_exists(MimeTypes::class)) {
                        throw new LogicException('You cannot validate the mime-type of files as the Mime component is not installed. Try running "composer require symfony/mime".');
                    }

                    $mimeTypesHelper = MimeTypes::getDefault();
                    $v = $mimeTypesHelper->getMimeTypes($k);
                }

                $mimeTypes = $mimeTypes ? array_intersect($v, $mimeTypes) : (array) $v;
                break;
            }

            if (!$found) {
                $this->context->buildViolation($constraint->extensionsMessage)
                    ->setParameter('{{ file }}', $this->formatValue($path))
                    ->setParameter('{{ extension }}', $this->formatValue($fileExtension))
                    ->setParameter('{{ extensions }}', $this->formatValues($normalizedExtensions))
                    ->setParameter('{{ name }}', $this->formatValue($basename))
                    ->setCode(File::INVALID_EXTENSION_ERROR)
                    ->addViolation();
            }
        }

        if ($mimeTypes) {
            if ($value instanceof FileObject) {
                $mime = $value->getMimeType();
            } elseif (isset($mimeTypesHelper) || class_exists(MimeTypes::class)) {
                $mime = ($mimeTypesHelper ?? MimeTypes::getDefault())->guessMimeType($path);
            } elseif (!class_exists(FileObject::class)) {
                throw new LogicException('You cannot validate the mime-type of files as the Mime component is not installed. Try running "composer require symfony/mime".');
            } else {
                $mime = (new FileObject($value))->getMimeType();
            }

            foreach ($mimeTypes as $mimeType) {
                if ($mimeType === $mime) {
                    return;
                }

                if ($discrete = strstr($mimeType, '/*', true)) {
                    if (strstr($mime, '/', true) === $discrete) {
                        return;
                    }
                }
            }

            $this->context->buildViolation($constraint->mimeTypesMessage)
                ->setParameter('{{ file }}', $this->formatValue($path))
                ->setParameter('{{ type }}', $this->formatValue($mime))
                ->setParameter('{{ types }}', $this->formatValues($mimeTypes))
                ->setParameter('{{ name }}', $this->formatValue($basename))
                ->setCode(File::INVALID_MIME_TYPE_ERROR)
                ->addViolation();
        }
    }

    private static function moreDecimalsThan(string $double, int $numberOfDecimals): bool
    {
        return \strlen($double) > \strlen(round($double, $numberOfDecimals));
    }

    /**
     * Convert the limit to the smallest possible number
     * (i.e. try "MB", then "kB", then "bytes").
     */
    private function factorizeSizes(int $size, int|float $limit, bool $binaryFormat): array
    {
        if ($binaryFormat) {
            $coef = self::MIB_BYTES;
            $coefFactor = self::KIB_BYTES;
        } else {
            $coef = self::MB_BYTES;
            $coefFactor = self::KB_BYTES;
        }

        // If $limit < $coef, $limitAsString could be < 1 with less than 3 decimals.
        // In this case, we would end up displaying an allowed size < 1 (eg: 0.1 MB).
        // It looks better to keep on factorizing (to display 100 kB for example).
        while ($limit < $coef) {
            $coef /= $coefFactor;
        }

        $limitAsString = (string) ($limit / $coef);

        // Restrict the limit to 2 decimals (without rounding! we
        // need the precise value)
        while (self::moreDecimalsThan($limitAsString, 2)) {
            $coef /= $coefFactor;
            $limitAsString = (string) ($limit / $coef);
        }

        // Convert size to the same measure, but round to 2 decimals
        $sizeAsString = (string) round($size / $coef, 2);

        // If the size and limit produce the same string output
        // (due to rounding), reduce the coefficient
        while ($sizeAsString === $limitAsString) {
            $coef /= $coefFactor;
            $limitAsString = (string) ($limit / $coef);
            $sizeAsString = (string) round($size / $coef, 2);
        }

        return [$sizeAsString, $limitAsString, self::SUFFICES[$coef]];
    }
}
