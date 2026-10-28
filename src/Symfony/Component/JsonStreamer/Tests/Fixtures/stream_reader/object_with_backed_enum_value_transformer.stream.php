<?php

declare(strict_types=1);

/**
 * @return Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithBackedEnumValueTransformer
 */
return static function (mixed $stream, \Psr\Container\ContainerInterface $transformers, \Symfony\Component\JsonStreamer\Read\LazyInstantiator $instantiator, array $options): mixed {
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithBackedEnumValueTransformer'] = static function ($stream, $offset, $length) use ($options, $transformers, $instantiator, &$providers) {
        $data = \Symfony\Component\JsonStreamer\Read\Splitter::splitDict($stream, $offset, $length);
        if (null === $data) {
            throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithBackedEnumValueTransformer'));
        }
        return $instantiator->instantiate(\Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithBackedEnumValueTransformer::class, static function ($object) use ($stream, $data, $options, $transformers, $instantiator, &$providers) {
            $properties = [];
            foreach ($data as $k => $v) {
                match ($k) {
                    'enum' => $properties['enum'] = Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithBackedEnumValueTransformer::enumToName((\Symfony\Component\JsonStreamer\Read\Decoder::isNull($stream, $v[0]) ? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithBackedEnumValueTransformer::$enum".') : $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum']($stream, $v[0], $v[1])), $options),
                    'nullableEnum' => $properties['nullableEnum'] = Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithBackedEnumValueTransformer::nullableEnumToName($providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum|null']($stream, $v[0], $v[1]), $options),
                    'requiredEnum' => $properties['requiredEnum'] = Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithBackedEnumValueTransformer::enumToName((\Symfony\Component\JsonStreamer\Read\Decoder::isNull($stream, $v[0]) ? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithBackedEnumValueTransformer::$requiredEnum".') : $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum']($stream, $v[0], $v[1])), $options),
                    default => null,
                };
            }
            foreach ($properties as $name => $value) {
                try {
                    $object->{$name} = $value;
                } catch (\TypeError $e) {
                    throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException($e->getMessage(), 0, $e);
                }
            }
        });
    };
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum'] = static function ($stream, $offset, $length) {
        return \Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum::from((\Symfony\Component\JsonStreamer\Read\Decoder::decodeStream($stream, $offset, $length) ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum".')));
    };
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum|null'] = static function ($stream, $offset, $length) use ($options, $transformers, $instantiator, &$providers) {
        $data = \Symfony\Component\JsonStreamer\Read\Decoder::decodeStream($stream, $offset, $length);
        if (\is_int($data)) {
            return $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum']($stream, $offset, $length);
        }
        if (null === $data) {
            return null;
        }
        throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum|null'));
    };
    return $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithBackedEnumValueTransformer']($stream, 0, null);
};
