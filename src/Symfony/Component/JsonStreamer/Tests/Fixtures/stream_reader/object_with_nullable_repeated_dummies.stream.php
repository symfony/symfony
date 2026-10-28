<?php

declare(strict_types=1);

/**
 * @return Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNullableRepeatedDummies
 */
return static function (mixed $stream, \Psr\Container\ContainerInterface $transformers, \Symfony\Component\JsonStreamer\Read\LazyInstantiator $instantiator, array $options): mixed {
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithNullableRepeatedDummies'] = static function ($stream, $offset, $length) use ($options, $transformers, $instantiator, &$providers) {
        $data = \Symfony\Component\JsonStreamer\Read\Splitter::splitDict($stream, $offset, $length);
        if (null === $data) {
            throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithNullableRepeatedDummies'));
        }
        return $instantiator->instantiate(\Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNullableRepeatedDummies::class, static function ($object) use ($stream, $data, $options, $transformers, $instantiator, &$providers) {
            $properties = [];
            foreach ($data as $k => $v) {
                match ($k) {
                    'first' => $properties['first'] = $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy|null']($stream, $v[0], $v[1]),
                    'second' => $properties['second'] = $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy|null']($stream, $v[0], $v[1]),
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
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy'] = static function ($stream, $offset, $length) use ($options, $transformers, $instantiator, &$providers) {
        $data = \Symfony\Component\JsonStreamer\Read\Splitter::splitDict($stream, $offset, $length);
        if (null === $data) {
            throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy'));
        }
        return $instantiator->instantiate(\Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy::class, static function ($object) use ($stream, $data, $options, $transformers, $instantiator, &$providers) {
            $properties = [];
            foreach ($data as $k => $v) {
                match ($k) {
                    'id' => $properties['id'] = (\Symfony\Component\JsonStreamer\Read\Decoder::decodeStream($stream, $v[0], $v[1]) ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy::$id".')),
                    'name' => $properties['name'] = (\Symfony\Component\JsonStreamer\Read\Decoder::decodeStream($stream, $v[0], $v[1]) ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy::$name".')),
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
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy|null'] = static function ($stream, $offset, $length) use ($options, $transformers, $instantiator, &$providers) {
        $data = \Symfony\Component\JsonStreamer\Read\Decoder::decodeStream($stream, $offset, $length);
        if (\is_array($data)) {
            return $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy']($stream, $offset, $length);
        }
        if (null === $data) {
            return null;
        }
        throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy|null'));
    };
    return $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithNullableRepeatedDummies']($stream, 0, null);
};
