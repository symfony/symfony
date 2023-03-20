<?php

return static function (string|\Stringable $string, array $config, \Symfony\Component\JsonEncoder\Decode\Instantiator $instantiator, ?\Psr\Container\ContainerInterface $services): mixed {
    $providers['Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNullableProperties'] = static function ($data) use ($config, $instantiator, $services, &$providers) {
        return $instantiator->instantiate(\Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNullableProperties::class, \array_filter(['name' => $data['name'] ?? '_symfony_missing_value', 'enum' => \array_key_exists('enum', $data) ? $providers['Symfony\Component\TypeInfo\Tests\Fixtures\DummyBackedEnum|null']($data['enum']) : '_symfony_missing_value'], static function ($v) {
            return '_symfony_missing_value' !== $v;
        }));
    };
    $providers['Symfony\Component\TypeInfo\Tests\Fixtures\DummyBackedEnum'] = static function ($data) {
        return \Symfony\Component\TypeInfo\Tests\Fixtures\DummyBackedEnum::from($data);
    };
    $providers['Symfony\Component\TypeInfo\Tests\Fixtures\DummyBackedEnum|null'] = static function ($data) use ($config, $instantiator, $services, &$providers) {
        if (\is_string($data)) {
            return $providers['Symfony\Component\TypeInfo\Tests\Fixtures\DummyBackedEnum']($data);
        }
        if (null === $data) {
            return null;
        }
        throw new \Symfony\Component\JsonEncoder\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "Symfony\Component\TypeInfo\Tests\Fixtures\DummyBackedEnum|null".', \get_debug_type($data)));
    };
    return $providers['Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNullableProperties'](\Symfony\Component\JsonEncoder\Decode\NativeDecoder::decodeString((string) $string));
};
