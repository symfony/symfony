<?php

return static function (mixed $stream, \Psr\Container\ContainerInterface $denormalizers, \Symfony\Component\JsonEncoder\Decode\LazyInstantiator $instantiator, array $options): mixed {
    $providers['array<int,Symfony\Component\JsonEncoder\Tests\Fixtures\Enum\DummyBackedEnum>'] = static function ($stream, $offset, $length) use ($options, $denormalizers, $instantiator, &$providers) {
        $data = \Symfony\Component\JsonEncoder\Decode\Splitter::splitList($stream, $offset, $length);
        $iterable = static function ($stream, $data) use ($options, $denormalizers, $instantiator, &$providers) {
            foreach ($data as $k => $v) {
                yield $k => $providers['Symfony\Component\JsonEncoder\Tests\Fixtures\Enum\DummyBackedEnum']($stream, $v[0], $v[1]);
            }
        };
        return \iterator_to_array($iterable($stream, $data));
    };
    $providers['Symfony\Component\JsonEncoder\Tests\Fixtures\Enum\DummyBackedEnum'] = static function ($stream, $offset, $length) {
        return \Symfony\Component\JsonEncoder\Tests\Fixtures\Enum\DummyBackedEnum::from(\Symfony\Component\JsonEncoder\Decode\NativeDecoder::decodeStream($stream, $offset, $length));
    };
    $providers['Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNameAttributes'] = static function ($stream, $offset, $length) use ($options, $denormalizers, $instantiator, &$providers) {
        $data = \Symfony\Component\JsonEncoder\Decode\Splitter::splitDict($stream, $offset, $length);
        $properties = [];
        foreach ($data as $k => $v) {
            match ($k) {
                '@id' => $properties['id'] = static function () use ($stream, $v, $options, $denormalizers, $instantiator, &$providers) {
                    return \Symfony\Component\JsonEncoder\Decode\NativeDecoder::decodeStream($stream, $v[0], $v[1]);
                },
                'name' => $properties['name'] = static function () use ($stream, $v, $options, $denormalizers, $instantiator, &$providers) {
                    return \Symfony\Component\JsonEncoder\Decode\NativeDecoder::decodeStream($stream, $v[0], $v[1]);
                },
                default => null,
            };
        }
        return $instantiator->instantiate(\Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNameAttributes::class, $properties);
    };
    $providers['Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNameAttributes|array<int,Symfony\Component\JsonEncoder\Tests\Fixtures\Enum\DummyBackedEnum>|int'] = static function ($stream, $offset, $length) use ($options, $denormalizers, $instantiator, &$providers) {
        $data = \Symfony\Component\JsonEncoder\Decode\NativeDecoder::decodeStream($stream, $offset, $length);
        if (\is_array($data) && \array_is_list($data)) {
            return $providers['array<int,Symfony\Component\JsonEncoder\Tests\Fixtures\Enum\DummyBackedEnum>']($data);
        }
        if (\is_array($data)) {
            return $providers['Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNameAttributes']($data);
        }
        if (\is_int($data)) {
            return $data;
        }
        throw new \Symfony\Component\JsonEncoder\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNameAttributes|array<int,Symfony\Component\JsonEncoder\Tests\Fixtures\Enum\DummyBackedEnum>|int".', \get_debug_type($data)));
    };
    return $providers['Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNameAttributes|array<int,Symfony\Component\JsonEncoder\Tests\Fixtures\Enum\DummyBackedEnum>|int']($stream, 0, null);
};
