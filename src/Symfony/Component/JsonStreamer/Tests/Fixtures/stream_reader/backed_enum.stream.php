<?php

return static function (mixed $stream, \Psr\Container\ContainerInterface $valueTransformers, \Symfony\Component\JsonStreamer\Read\LazyInstantiator $instantiator, array $options): mixed {
    $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum'] = static function ($stream, $offset, $length) {
        return \Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum::from(\Symfony\Component\JsonStreamer\Read\Decoder::decodeStream($stream, $offset, $length));
    };
    return $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum']($stream, 0, null);
};
