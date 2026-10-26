<?php

/**
 * @return Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNullableProperties
 */
return static function (string|\Stringable $string, \Psr\Container\ContainerInterface $transformers, \Symfony\Component\JsonStreamer\Read\Instantiator $instantiator, array $options): mixed {
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithNullableProperties'] = static function ($data) use ($options, $transformers, $instantiator, &$providers) {
        if (!\is_array($data)) {
            throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithNullableProperties'));
        }
        return $instantiator->instantiate(\Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNullableProperties::class, [...(\array_key_exists('name', $data) ? ['name' => $data['name']] : []), ...(\array_key_exists('enum', $data) ? ['enum' => $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum|null']($data['enum'])] : [])]);
    };
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum'] = static function ($data) {
        return \Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum::from(($data ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum".')));
    };
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum|null'] = static function ($data) use ($options, $transformers, $instantiator, &$providers) {
        if (\is_int($data)) {
            return $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum']($data);
        }
        if (null === $data) {
            return null;
        }
        throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum|null'));
    };
    return $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithNullableProperties'](\Symfony\Component\JsonStreamer\Read\Decoder::decodeString((string) $string));
};
