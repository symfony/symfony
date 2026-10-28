<?php

declare(strict_types=1);

/**
 * @return Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNameAttributes|int|list<Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum>
 */
return static function (mixed $stream, \Psr\Container\ContainerInterface $transformers, \Symfony\Component\JsonStreamer\Read\LazyInstantiator $instantiator, array $options): mixed {
    $providers['list<Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum>'] = static function ($stream, $offset, $length) use ($options, $transformers, $instantiator, &$providers) {
        $data = \Symfony\Component\JsonStreamer\Read\Splitter::splitList($stream, $offset, $length);
        if (null === $data) {
            throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'list<Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum>'));
        }
        $collection = [];
        foreach ($data as $k => $v) {
            $collection[$k] = $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum']($stream, $v[0], $v[1]);
        }
        return $collection;
    };
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum'] = static function ($stream, $offset, $length) {
        return \Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum::from((\Symfony\Component\JsonStreamer\Read\Decoder::decodeStream($stream, $offset, $length) ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum".')));
    };
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithNameAttributes'] = static function ($stream, $offset, $length) use ($options, $transformers, $instantiator, &$providers) {
        $data = \Symfony\Component\JsonStreamer\Read\Splitter::splitDict($stream, $offset, $length);
        if (null === $data) {
            throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithNameAttributes'));
        }
        return $instantiator->instantiate(\Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNameAttributes::class, static function ($object) use ($stream, $data, $options, $transformers, $instantiator, &$providers) {
            $properties = [];
            foreach ($data as $k => $v) {
                match ($k) {
                    '@id' => $properties['id'] = (\Symfony\Component\JsonStreamer\Read\Decoder::decodeStream($stream, $v[0], $v[1]) ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithNameAttributes::$id".')),
                    'name' => $properties['name'] = (\Symfony\Component\JsonStreamer\Read\Decoder::decodeStream($stream, $v[0], $v[1]) ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithNameAttributes::$name".')),
                    default => \Symfony\Component\JsonStreamer\Read\Decoder::validateStream($stream, $v[0], $v[1]),
                };
            }
            foreach ($properties as $name => $value) {
                try {
                    $object->{$name} = $value;
                } catch (\TypeError $e) {
                    throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException($e->getMessage(), 0, $e);
                }
            }
        });
    };
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithNameAttributes|int|list<Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum>'] = static function ($stream, $offset, $length) use ($options, $transformers, $instantiator, &$providers) {
        $data = \Symfony\Component\JsonStreamer\Read\Decoder::decodeStream($stream, $offset, $length);
        if (\is_array($data) && \array_is_list($data)) {
            return $providers['list<Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum>']($stream, $offset, $length);
        }
        if (\is_array($data)) {
            return $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithNameAttributes']($stream, $offset, $length);
        }
        if (\is_int($data)) {
            return $data;
        }
        throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithNameAttributes|int|list<Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum>'));
    };
    return $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithNameAttributes|int|list<Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum>']($stream, 0, null);
};
