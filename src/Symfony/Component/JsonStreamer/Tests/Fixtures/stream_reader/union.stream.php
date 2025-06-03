<?php

return static function (mixed $stream, \Psr\Container\ContainerInterface $valueTransformers, \Symfony\Component\JsonStreamer\Read\LazyInstantiator $instantiator, array $options): mixed {
    $providers['array<int,Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum>'] = static function ($stream, $offset, $length) use ($options, $valueTransformers, $instantiator, &$providers) {
        $data = \Symfony\Component\JsonStreamer\Read\Splitter::splitList($stream, $offset, $length);
        $iterable = static function ($stream, $data) use ($options, $valueTransformers, $instantiator, &$providers) {
            foreach ($data as $k => $v) {
                yield $k => $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum']($stream, $v[0], $v[1]);
            }
        };
        return \iterator_to_array($iterable($stream, $data));
    };
    $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum'] = static function ($stream, $offset, $length) {
        return \Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum::from(\Symfony\Component\JsonStreamer\Read\Decoder::decodeStream($stream, $offset, $length));
    };
    $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNameAttributes'] = static function ($stream, $offset, $length) use ($options, $valueTransformers, $instantiator, &$providers) {
        $data = \Symfony\Component\JsonStreamer\Read\Splitter::splitDict($stream, $offset, $length);
        return $instantiator->instantiate(\Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNameAttributes::class, static function ($object) use ($stream, $data, $options, $valueTransformers, $instantiator, &$providers) {
            foreach ($data as $k => $v) {
                match ($k) {
                    '@id' => $object->id = \Symfony\Component\JsonStreamer\Read\Decoder::decodeStream($stream, $v[0], $v[1]),
                    'name' => $object->name = \Symfony\Component\JsonStreamer\Read\Decoder::decodeStream($stream, $v[0], $v[1]),
                    default => null,
                };
            }
        });
    };
    $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNameAttributes|array<int,Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum>|int'] = static function ($stream, $offset, $length) use ($options, $valueTransformers, $instantiator, &$providers) {
        $data = \Symfony\Component\JsonStreamer\Read\Decoder::decodeStream($stream, $offset, $length);
        if (\is_array($data) && \array_is_list($data)) {
            return $providers['array<int,Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum>']($data);
        }
        if (\is_array($data)) {
            return $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNameAttributes']($data);
        }
        if (\is_int($data)) {
            return $data;
        }
        throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNameAttributes|array<int,Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum>|int".', \get_debug_type($data)));
    };
    return $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNameAttributes|array<int,Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum>|int']($stream, 0, null);
};
