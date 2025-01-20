<?php

return static function (string|\Stringable $string, \Psr\Container\ContainerInterface $denormalizers, \Symfony\Component\JsonEncoder\Decode\Instantiator $instantiator, array $options): mixed {
    $providers['Symfony\Component\JsonEncoder\Tests\Fixtures\Enum\DummyBackedEnum'] = static function ($data) {
        return \Symfony\Component\JsonEncoder\Tests\Fixtures\Enum\DummyBackedEnum::from($data);
    };
    return $providers['Symfony\Component\JsonEncoder\Tests\Fixtures\Enum\DummyBackedEnum'](\Symfony\Component\JsonEncoder\Decode\NativeDecoder::decodeString((string) $string));
};
