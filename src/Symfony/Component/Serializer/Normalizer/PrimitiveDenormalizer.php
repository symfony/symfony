<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Normalizer;

use Symfony\Component\Serializer\Exception\NotNormalizableValueException;

/**
 * @author Ian Bentley <ian@idbentley.com>
 */
final class PrimitiveDenormalizer implements DenormalizerInterface
{

    public function getSupportedTypes(?string $format): array
    {
        return ['*' => true];
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        if ($type === 'int' || $type === 'float' || $type === 'string' || $type === 'bool' || $type === 'null') {
            return true;
        }
        return false;
    }

    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        // In XML and CSV all basic datatypes are represented as strings, it is e.g. not possible to determine,
        // if a value is meant to be a string, float, int or a boolean value from the serialized representation.
        // That's why we have to transform the values, if one of these non-string basic datatypes is expected.
        if (\is_string($data) && (XmlEncoder::FORMAT === $format || CsvEncoder::FORMAT === $format)) {
            if ('' === $data) {
                if (LegacyType::BUILTIN_TYPE_STRING === $builtinType) {
                    return '';
                }
            }

            switch ($type) {
                case 'bool':
                    // according to https://www.w3.org/TR/xmlschema-2/#boolean, valid representations are "false", "true", "0" and "1"
                    if ('false' === $data || '0' === $data) {
                        return false;
                    } elseif ('true' === $data || '1' === $data) {
                        return true;
                    } else {
                        throw NotNormalizableValueException::createForUnexpectedDataType(sprintf('Data of type bool expected ("%s" given).', $data));
                    }
                    break;
                case "int":
                    if (ctype_digit(isset($data[0]) && '-' === $data[0] ? substr($data, 1) : $data)) {
                        $data = (int) $data;
                    } else {
                        throw NotNormalizableValueException::createForUnexpectedDataType(sprintf('Data of type int expected ("%s" given).', $data));
                    }
                    break;
                case "float":
                    if (is_numeric($data)) {
                        return (float) $data;
                    }

                    return match ($data) {
                        'NaN' => \NAN,
                        'INF' => \INF,
                        '-INF' => -\INF,
                        default => throw NotNormalizableValueException::createForUnexpectedDataType(sprintf('Data of type float expected ("%s" given).', $data)),
                    };
                case "string":
                    return $data;
                case "null":
                    return null;
            }
        }

    }
}
