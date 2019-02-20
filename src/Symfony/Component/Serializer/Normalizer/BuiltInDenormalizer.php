<?php


namespace Symfony\Component\Serializer\Normalizer;


use Symfony\Component\PropertyInfo\Type;
use Symfony\Component\Serializer\Encoder\CsvEncoder;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use Symfony\Component\Serializer\Exception\BadMethodCallException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Symfony\Component\Serializer\Exception\InvalidArgumentException;
use Symfony\Component\Serializer\Exception\LogicException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Serializer\Exception\RuntimeException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;

class BuiltInDenormalizer implements DenormalizerInterface
{
    protected $defaultContext = [];

    public function __construct(array $defaultContext = [])
    {
        $this->defaultContext = array_merge($defaultContext, $defaultContext);
    }

    public function denormalize($data, $class, $format = null, array $context = [])
    {
        $nullable = $context['value_nullable'] ?? false;
        if ($nullable && null === $data) {
            return null;
        }

        // This is a fast path to avoid checks when the data is already correct
        if (('\is_'.$class)($data)) {
            return $data;
        }

        // JSON only has a Number type corresponding to both int and float PHP types.
        // PHP's json_encode, JavaScript's JSON.stringify, Go's json.Marshal as well as most other JSON encoders convert
        // floating-point numbers like 12.0 to 12 (the decimal part is dropped when possible).
        // PHP's json_decode automatically converts Numbers without a decimal part to integers.
        // To circumvent this behavior, integers are converted to floats when denormalizing JSON based formats and when
        // a float is expected.
        if (Type::BUILTIN_TYPE_FLOAT === $class && \is_int($data) && false !== strpos($format, JsonEncoder::FORMAT)) {
            return (float) $data;
        }

        // XML and CSV formats dont represent ints, floats or bools, so we convert strings into these formats
        // Also null and empty string are not different, so we convert null to ''
        if ($format === XmlEncoder::FORMAT || $format === CsvEncoder::FORMAT) {
            if (Type::BUILTIN_TYPE_INT === $class && \is_string($data) && (string)(int) $data === $data) {
                return (int) $data;
            }
            if (Type::BUILTIN_TYPE_FLOAT === $class && \is_string($data) && (string)(float) $data === $data) {
                return (float) $data;
            }
            if (Type::BUILTIN_TYPE_BOOL === $class && ($data === '1' || $data === '0')) {
                return $data === '1' ? true : false;
            }
            if (Type::BUILTIN_TYPE_STRING === $class && null === $data) {
                return $nullable ? null : '';
            }
        }

        if (('\is_'.$class)($data)) {
            return $data;
        }

        if ($context[AbstractObjectNormalizer::DISABLE_TYPE_ENFORCEMENT] ?? $this->defaultContext[AbstractObjectNormalizer::DISABLE_TYPE_ENFORCEMENT] ?? false) {
            return $data;
        }

        throw new NotNormalizableValueException(sprintf('The type of the data must be "%s" ("%s" given).', $class, \gettype($data)));
    }

    public function supportsDenormalization($data, $type, $format = null)
    {
        return in_array($type, [Type::BUILTIN_TYPE_INT, Type::BUILTIN_TYPE_FLOAT, Type::BUILTIN_TYPE_STRING, TYPE::BUILTIN_TYPE_BOOL, Type::BUILTIN_TYPE_ARRAY]);
    }
}
