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

use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;

final class BuiltinTypeDenormalizer implements DenormalizerInterface, CacheableSupportsMethodInterface
{
    private const TYPE_INT = 'int';
    private const TYPE_FLOAT = 'float';
    private const TYPE_STRING = 'string';
    private const TYPE_BOOL = 'bool';
    private const TYPE_RESOURCE = 'resource';
    private const TYPE_CALLABLE = 'callable';

    private const SUPPORTED_TYPES = [
        self::TYPE_INT => true,
        self::TYPE_BOOL => true,
        self::TYPE_FLOAT => true,
        self::TYPE_STRING => true,
        self::TYPE_RESOURCE => true,
        self::TYPE_CALLABLE => true,
    ];

    /**
     * {@inheritdoc}
     */
    public function denormalize($data, string $type, string $format = null, array $context = [])
    {
        $dataType = get_debug_type($data);

        if (!(isset(self::SUPPORTED_TYPES[$dataType]) || 0 === strpos($dataType, self::TYPE_RESOURCE) || \is_callable($data))) {
            throw new InvalidArgumentException(sprintf('Data expected to be of one of the types in "%s" ("%s" given).', implode(', ', array_keys(self::SUPPORTED_TYPES)), get_debug_type($data)));
        }

        // In XML and CSV all basic datatypes are represented as strings, it is e.g. not possible to determine,
        // if a value is meant to be a string, float, int or a boolean value from the serialized representation.
        // That's why we have to transform the values, if one of these non-string basic datatypes is expected.
        if (\is_string($data) && (XmlEncoder::FORMAT === $format || CsvEncoder::FORMAT === $format)) {
            switch ($type) {
                case self::TYPE_BOOL:
                    // according to https://www.w3.org/TR/xmlschema-2/#boolean, valid representations are "false", "true", "0" and "1"
                    if ('false' === $data || '0' === $data) {
                        return false;
                    }
                    if ('true' === $data || '1' === $data) {
                        return true;
                    }

                    throw new NotNormalizableValueException(sprintf('Data expected to be of type "%s" ("%s" given).', $type, $data));
                case self::TYPE_INT:
                    if (ctype_digit($data) || '-' === $data[0] && ctype_digit(substr($data, 1))) {
                        return (int) $data;
                    }

                    throw new NotNormalizableValueException(sprintf('Data expected to be of type "%s" ("%s" given).', $type, $data));
                case self::TYPE_FLOAT:
                    if (is_numeric($data)) {
                        return (float) $data;
                    }

                    switch ($data) {
                        case 'NaN':
                            return \NAN;
                        case 'INF':
                            return \INF;
                        case '-INF':
                            return -\INF;
                        default:
                            throw new NotNormalizableValueException(sprintf('Data expected to be of type "%s" ("%s" given).', $type, $data));
                    }
            }
        }

        // JSON only has a Number type corresponding to both int and float PHP types.
        // PHP's json_encode, JavaScript's JSON.stringify, Go's json.Marshal as well as most other JSON encoders convert
        // floating-point numbers like 12.0 to 12 (the decimal part is dropped when possible).
        // PHP's json_decode automatically converts Numbers without a decimal part to integers.
        // To circumvent this behavior, integers are converted to floats when denormalizing JSON based formats and when
        // a float is expected.
        if (self::TYPE_FLOAT === $type && \is_int($data) && false !== strpos($format, JsonEncoder::FORMAT)) {
            return (float) $data;
        }

        if (!('is_'.$type)($data)) {
            throw new NotNormalizableValueException(sprintf('Data expected to be of type "%s" ("%s" given).', $type, get_debug_type($data)));
        }

        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsDenormalization($data, string $type, string $format = null)
    {
        return isset(self::SUPPORTED_TYPES[$type]);
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return true;
    }
}
