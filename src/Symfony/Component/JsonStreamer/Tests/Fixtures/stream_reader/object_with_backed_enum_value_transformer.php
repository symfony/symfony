<?php

declare(strict_types=1);

/**
 * @return Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithBackedEnumValueTransformer
 */
return static function (string|\Stringable $string, \Psr\Container\ContainerInterface $transformers, array $options): mixed {
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithBackedEnumValueTransformer'] = static function ($data) use ($options, $transformers, &$providers) {
        if (!\is_array($data)) {
            throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithBackedEnumValueTransformer'));
        }
        $object = new \Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithBackedEnumValueTransformer();
        if (\array_key_exists('enum', $data)) {
            $value = Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithBackedEnumValueTransformer::enumToName($providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum'](($data['enum'] ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithBackedEnumValueTransformer::$enum".'))), $options);
            try {
                $object->{'enum'} = $value;
            } catch (\TypeError $e) {
                throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException($e->getMessage(), 0, $e);
            }
        }
        if (\array_key_exists('nullableEnum', $data)) {
            $value = Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithBackedEnumValueTransformer::nullableEnumToName($providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum|null']($data['nullableEnum']), $options);
            try {
                $object->{'nullableEnum'} = $value;
            } catch (\TypeError $e) {
                throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException($e->getMessage(), 0, $e);
            }
        }
        if (\array_key_exists('requiredEnum', $data)) {
            $value = Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithBackedEnumValueTransformer::enumToName($providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum'](($data['requiredEnum'] ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithBackedEnumValueTransformer::$requiredEnum".'))), $options);
            try {
                $object->{'requiredEnum'} = $value;
            } catch (\TypeError $e) {
                throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException($e->getMessage(), 0, $e);
            }
        }
        return $object;
    };
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum'] = static function ($data) {
        return \Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum::from(($data ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum".')));
    };
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum|null'] = static function ($data) use ($options, $transformers, &$providers) {
        if (\is_int($data)) {
            return $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum']($data);
        }
        if (null === $data) {
            return null;
        }
        throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum|null'));
    };
    return $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithBackedEnumValueTransformer'](\Symfony\Component\JsonStreamer\Read\Decoder::decodeString((string) $string));
};
