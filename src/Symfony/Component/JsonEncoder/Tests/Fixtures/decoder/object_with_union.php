<?php

return static function (string|\Stringable $string, \Psr\Container\ContainerInterface $denormalizers, \Symfony\Component\JsonEncoder\Decode\Instantiator $instantiator, array $options): mixed {
    $providers['Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithUnionProperties'] = static function ($data) use ($options, $denormalizers, $instantiator, &$providers) {
        return $instantiator->instantiate(\Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithUnionProperties::class, \array_filter(['value' => \array_key_exists('value', $data) ? $providers['Symfony\Component\JsonEncoder\Tests\Fixtures\Enum\DummyBackedEnum|null|string']($data['value']) : '_symfony_missing_value'], static function ($v) {
            return '_symfony_missing_value' !== $v;
        }));
    };
    $providers['Symfony\Component\JsonEncoder\Tests\Fixtures\Enum\DummyBackedEnum'] = static function ($data) {
        return \Symfony\Component\JsonEncoder\Tests\Fixtures\Enum\DummyBackedEnum::from($data);
    };
    $providers['Symfony\Component\JsonEncoder\Tests\Fixtures\Enum\DummyBackedEnum|null|string'] = static function ($data) use ($options, $denormalizers, $instantiator, &$providers) {
        if (\is_int($data)) {
            return $providers['Symfony\Component\JsonEncoder\Tests\Fixtures\Enum\DummyBackedEnum']($data);
        }
        if (null === $data) {
            return null;
        }
        if (\is_string($data)) {
            return $data;
        }
        throw new \Symfony\Component\JsonEncoder\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "Symfony\Component\JsonEncoder\Tests\Fixtures\Enum\DummyBackedEnum|null|string".', \get_debug_type($data)));
    };
    return $providers['Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithUnionProperties'](\Symfony\Component\JsonEncoder\Decode\NativeDecoder::decodeString((string) $string));
};
