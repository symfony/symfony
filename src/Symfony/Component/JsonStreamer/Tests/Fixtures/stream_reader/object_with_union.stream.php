<?php

return static function (mixed $stream, \Psr\Container\ContainerInterface $valueTransformers, \Symfony\Component\JsonStreamer\Read\LazyInstantiator $instantiator, array $options): mixed {
    $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithUnionProperties'] = static function ($stream, $offset, $length) use ($options, $valueTransformers, $instantiator, &$providers) {
        $data = \Symfony\Component\JsonStreamer\Read\Splitter::splitDict($stream, $offset, $length);
        return $instantiator->instantiate(\Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithUnionProperties::class, static function ($object) use ($stream, $data, $options, $valueTransformers, $instantiator, &$providers) {
            foreach ($data as $k => $v) {
                match ($k) {
                    'value' => $object->value = $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum|null|string']($stream, $v[0], $v[1]),
                    default => null,
                };
            }
        });
    };
    $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum'] = static function ($stream, $offset, $length) {
        return \Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum::from(\Symfony\Component\JsonStreamer\Read\Decoder::decodeStream($stream, $offset, $length));
    };
    $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum|null|string'] = static function ($stream, $offset, $length) use ($options, $valueTransformers, $instantiator, &$providers) {
        $data = \Symfony\Component\JsonStreamer\Read\Decoder::decodeStream($stream, $offset, $length);
        if (\is_int($data)) {
            return $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum']($data);
        }
        if (null === $data) {
            return null;
        }
        if (\is_string($data)) {
            return $data;
        }
        throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum|null|string".', \get_debug_type($data)));
    };
    return $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithUnionProperties']($stream, 0, null);
};
