<?php

/**
 * @return Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNameAttributes|int|list<Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum>
 */
return static function (string|\Stringable $string, \Psr\Container\ContainerInterface $valueTransformers, \Symfony\Component\JsonStreamer\Read\Instantiator $instantiator, array $options): mixed {
    $providers['list<Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum>'] = static function ($data) use ($options, $valueTransformers, $instantiator, &$providers) {
        if (!\is_array($data)) {
            throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'list<Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum>'));
        }
        $iterable = static function ($data) use ($options, $valueTransformers, $instantiator, &$providers) {
            foreach ($data as $k => $v) {
                yield $k => $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum']($v);
            }
        };
        return \iterator_to_array($iterable($data));
    };
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum'] = static function ($data) {
        return \Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum::from(($data ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum".')));
    };
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithNameAttributes'] = static function ($data) use ($options, $valueTransformers, $instantiator, &$providers) {
        if (!\is_array($data)) {
            throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithNameAttributes'));
        }
        return $instantiator->instantiate(\Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNameAttributes::class, [...(\array_key_exists('@id', $data) ? ['id' => ($data['@id'] ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithNameAttributes::$id".'))] : []), ...(\array_key_exists('name', $data) ? ['name' => ($data['name'] ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithNameAttributes::$name".'))] : [])]);
    };
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithNameAttributes|int|list<Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum>'] = static function ($data) use ($options, $valueTransformers, $instantiator, &$providers) {
        if (\is_array($data) && \array_is_list($data)) {
            return $providers['list<Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum>']($data);
        }
        if (\is_array($data)) {
            return $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithNameAttributes']($data);
        }
        if (\is_int($data)) {
            return $data;
        }
        throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithNameAttributes|int|list<Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum>'));
    };
    return $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithNameAttributes|int|list<Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum>'](\Symfony\Component\JsonStreamer\Read\Decoder::decodeString((string) $string));
};
