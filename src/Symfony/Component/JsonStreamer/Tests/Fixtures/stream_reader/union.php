<?php

return static function (string|\Stringable $string, \Psr\Container\ContainerInterface $valueTransformers, \Symfony\Component\JsonStreamer\Read\Instantiator $instantiator, array $options): mixed {
    $providers['array<int,Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum>'] = static function ($data) use ($options, $valueTransformers, $instantiator, &$providers) {
        $iterable = static function ($data) use ($options, $valueTransformers, $instantiator, &$providers) {
            foreach ($data as $k => $v) {
                yield $k => $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum']($v);
            }
        };
        return \iterator_to_array($iterable($data));
    };
    $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum'] = static function ($data) {
        return \Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum::from($data);
    };
    $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNameAttributes'] = static function ($data) use ($options, $valueTransformers, $instantiator, &$providers) {
        return $instantiator->instantiate(\Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNameAttributes::class, \array_filter(['id' => $data['@id'] ?? '_symfony_missing_value', 'name' => $data['name'] ?? '_symfony_missing_value'], static function ($v) {
            return '_symfony_missing_value' !== $v;
        }));
    };
    $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNameAttributes|array<int,Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum>|int'] = static function ($data) use ($options, $valueTransformers, $instantiator, &$providers) {
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
    return $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNameAttributes|array<int,Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum>|int'](\Symfony\Component\JsonStreamer\Read\Decoder::decodeString((string) $string));
};
