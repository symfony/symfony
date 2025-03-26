<?php

return static function (mixed $stream, \Psr\Container\ContainerInterface $valueTransformers, \Symfony\Component\JsonStreamer\Read\LazyInstantiator $instantiator, array $options): mixed {
    $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum'] = static function ($stream, $offset, $length) {
        return \Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum::from(\Symfony\Component\JsonStreamer\Read\Decoder::decodeStream($stream, $offset, $length));
    };
    $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum|null'] = static function ($stream, $offset, $length) use ($options, $valueTransformers, $instantiator, &$providers) {
        $data = \Symfony\Component\JsonStreamer\Read\Decoder::decodeStream($stream, $offset, $length);
        if (\is_int($data)) {
            return $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum']($data);
        }
        if (null === $data) {
            return null;
        }
        throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum|null".', \get_debug_type($data)));
    };
    return $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum|null']($stream, 0, null);
};
