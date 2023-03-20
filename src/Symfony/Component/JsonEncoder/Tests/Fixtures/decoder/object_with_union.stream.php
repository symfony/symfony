<?php

return static function (mixed $stream, \Psr\Container\ContainerInterface $denormalizers, \Symfony\Component\JsonEncoder\Decode\LazyInstantiator $instantiator, array $options): mixed {
    $providers['Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithUnionProperties'] = static function ($stream, $offset, $length) use ($options, $denormalizers, $instantiator, &$providers) {
        $data = \Symfony\Component\JsonEncoder\Decode\Splitter::splitDict($stream, $offset, $length);
        $properties = [];
        foreach ($data as $k => $v) {
            match ($k) {
                'value' => $properties['value'] = static function () use ($stream, $v, $options, $denormalizers, $instantiator, &$providers) {
                    return $providers['Symfony\Component\JsonEncoder\Tests\Fixtures\Enum\DummyBackedEnum|null|string']($stream, $v[0], $v[1]);
                },
                default => null,
            };
        }
        return $instantiator->instantiate(\Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithUnionProperties::class, $properties);
    };
    $providers['Symfony\Component\JsonEncoder\Tests\Fixtures\Enum\DummyBackedEnum'] = static function ($stream, $offset, $length) {
        return \Symfony\Component\JsonEncoder\Tests\Fixtures\Enum\DummyBackedEnum::from(\Symfony\Component\JsonEncoder\Decode\NativeDecoder::decodeStream($stream, $offset, $length));
    };
    $providers['Symfony\Component\JsonEncoder\Tests\Fixtures\Enum\DummyBackedEnum|null|string'] = static function ($stream, $offset, $length) use ($options, $denormalizers, $instantiator, &$providers) {
        $data = \Symfony\Component\JsonEncoder\Decode\NativeDecoder::decodeStream($stream, $offset, $length);
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
    return $providers['Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithUnionProperties']($stream, 0, null);
};
