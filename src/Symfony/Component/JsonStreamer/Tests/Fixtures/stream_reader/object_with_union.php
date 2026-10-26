<?php

/**
 * @return Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithUnionProperties
 */
return static function (string|\Stringable $string, \Psr\Container\ContainerInterface $transformers, \Symfony\Component\JsonStreamer\Read\Instantiator $instantiator, array $options): mixed {
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithUnionProperties'] = static function ($data) use ($options, $transformers, $instantiator, &$providers) {
        if (!\is_array($data)) {
            throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithUnionProperties'));
        }
        return $instantiator->instantiate(\Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithUnionProperties::class, [...(\array_key_exists('value', $data) ? ['value' => $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum|null|string']($data['value'])] : [])]);
    };
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum'] = static function ($data) {
        return \Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum::from(($data ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum".')));
    };
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum|null|string'] = static function ($data) use ($options, $transformers, $instantiator, &$providers) {
        if (\is_int($data)) {
            return $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum']($data);
        }
        if (null === $data) {
            return null;
        }
        if (\is_string($data)) {
            return $data;
        }
        throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum|null|string'));
    };
    return $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithUnionProperties'](\Symfony\Component\JsonStreamer\Read\Decoder::decodeString((string) $string));
};
