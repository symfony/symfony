<?php

return static function (mixed $stream, array $config, \Symfony\Component\JsonEncoder\Decode\LazyInstantiator $instantiator, ?\Psr\Container\ContainerInterface $services): mixed {
    $providers['Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNullableProperties'] = static function ($stream, $offset, $length) use ($config, $instantiator, $services, &$providers) {
        $data = \Symfony\Component\JsonEncoder\Decode\Splitter::splitDict($stream, $offset, $length);
        $properties = [];
        foreach ($data as $k => $v) {
            match ($k) {
                'name' => $properties['name'] = static function () use ($stream, $v, $config, $instantiator, $services, &$providers) {
                    return \Symfony\Component\JsonEncoder\Decode\NativeDecoder::decodeStream($stream, $v[0], $v[1]);
                },
                'enum' => $properties['enum'] = static function () use ($stream, $v, $config, $instantiator, $services, &$providers) {
                    return $providers['Symfony\Component\TypeInfo\Tests\Fixtures\DummyBackedEnum|null']($stream, $v[0], $v[1]);
                },
                default => null,
            };
        }
        return $instantiator->instantiate(\Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNullableProperties::class, $properties);
    };
    $providers['Symfony\Component\TypeInfo\Tests\Fixtures\DummyBackedEnum'] = static function ($stream, $offset, $length) {
        return \Symfony\Component\TypeInfo\Tests\Fixtures\DummyBackedEnum::from(\Symfony\Component\JsonEncoder\Decode\NativeDecoder::decodeStream($stream, $offset, $length));
    };
    $providers['Symfony\Component\TypeInfo\Tests\Fixtures\DummyBackedEnum|null'] = static function ($stream, $offset, $length) use ($config, $instantiator, $services, &$providers) {
        $data = \Symfony\Component\JsonEncoder\Decode\NativeDecoder::decodeStream($stream, $offset, $length);
        if (\is_string($data)) {
            return $providers['Symfony\Component\TypeInfo\Tests\Fixtures\DummyBackedEnum']($data);
        }
        if (null === $data) {
            return null;
        }
        throw new \Symfony\Component\JsonEncoder\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "Symfony\Component\TypeInfo\Tests\Fixtures\DummyBackedEnum|null".', \get_debug_type($data)));
    };
    return $providers['Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNullableProperties']($stream, 0, null);
};
