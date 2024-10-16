<?php

namespace Symfony\Component\Serializer\Normalizer;

/**
 * @method array getSupportedTypes(?string $format)
 */
class ScalarNormalizer implements NormalizerInterface
{
    public const TRUE_VALUE_KEY = 'true_value';
    public const FALSE_VALUE_KEY = 'false_value';

    /**
     * @inheritDoc
     */
    public function normalize(mixed $object, ?string $format = null, array $context = [])
    {
        if (true === $object && isset($context[self::TRUE_VALUE_KEY])) {
            return $context[self::TRUE_VALUE_KEY];
        }
        if (false === $object && isset($context[self::FALSE_VALUE_KEY])) {
            return $context[self::FALSE_VALUE_KEY];
        }

        return $object;
    }

    /**
     * @inheritDoc
     */
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        if (true === $data) {
            return isset($context[self::TRUE_VALUE_KEY]);
        }
        if (false === $data) {
            return isset($context[self::FALSE_VALUE_KEY]);
        }
        return is_scalar($data);
    }
}
