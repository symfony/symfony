<?php

return static function (string|\Stringable $string, array $config, \Symfony\Component\JsonEncoder\Decode\Instantiator $instantiator, ?\Psr\Container\ContainerInterface $services): mixed {
    $providers['Symfony\Component\JsonEncoder\Tests\Fixtures\Enum\DummyBackedEnum'] = static function ($data) {
        return \Symfony\Component\JsonEncoder\Tests\Fixtures\Enum\DummyBackedEnum::from($data);
    };
    $providers['Symfony\Component\JsonEncoder\Tests\Fixtures\Enum\DummyBackedEnum|null'] = static function ($data) use ($config, $instantiator, $services, &$providers) {
        if (\is_int($data)) {
            return $providers['Symfony\Component\JsonEncoder\Tests\Fixtures\Enum\DummyBackedEnum']($data);
        }
        if (null === $data) {
            return null;
        }
        throw new \Symfony\Component\JsonEncoder\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "Symfony\Component\JsonEncoder\Tests\Fixtures\Enum\DummyBackedEnum|null".', \get_debug_type($data)));
    };
    return $providers['Symfony\Component\JsonEncoder\Tests\Fixtures\Enum\DummyBackedEnum|null'](\Symfony\Component\JsonEncoder\Decode\NativeDecoder::decodeString((string) $string));
};
