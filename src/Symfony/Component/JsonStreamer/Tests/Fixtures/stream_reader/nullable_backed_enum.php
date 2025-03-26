<?php

return static function (string|\Stringable $string, \Psr\Container\ContainerInterface $valueTransformers, \Symfony\Component\JsonStreamer\Read\Instantiator $instantiator, array $options): mixed {
    $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum'] = static function ($data) {
        return \Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum::from($data);
    };
    $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum|null'] = static function ($data) use ($options, $valueTransformers, $instantiator, &$providers) {
        if (\is_int($data)) {
            return $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum']($data);
        }
        if (null === $data) {
            return null;
        }
        throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum|null".', \get_debug_type($data)));
    };
    return $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum|null'](\Symfony\Component\JsonStreamer\Read\Decoder::decodeString((string) $string));
};
