<?php

/**
 * @return list<Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy|list<int>|null>
 */
return static function (mixed $stream, \Psr\Container\ContainerInterface $transformers, \Symfony\Component\JsonStreamer\Read\LazyInstantiator $instantiator, array $options): mixed {
    $providers['list<Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy|list<int>|null>'] = static function ($stream, $offset, $length) use ($options, $transformers, $instantiator, &$providers, &$readers) {
        $data = \Symfony\Component\JsonStreamer\Read\Splitter::splitList($stream, $offset, $length);
        if (null === $data) {
            throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'list<Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy|list<int>|null>'));
        }
        $collection = [];
        foreach ($data as $k => $v) {
            $collection[$k] = $readers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy|list<int>|null']($stream, $v[0], $v[1]);
        }
        return $collection;
    };
    $denormalizers['list<int>'] = static function ($data) use ($options, $transformers, &$denormalizers) {
        if (!(\is_array($data))) {
            throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'list<int>'));
        }
        $collection = [];
        foreach ($data as $k => $v) {
            $collection[$k] = (null === $v || \is_scalar($v) ? $v : throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($v), 'int')));
        }
        return $collection;
    };
    $denormalizers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy'] = static function ($data) use ($options, $transformers, &$denormalizers) {
        if (!$data instanceof \stdClass) {
            throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy'));
        }
        $data = (array) $data;
        $object = (new \ReflectionClass(\Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy::class))->newInstanceWithoutConstructor();
        if (\array_key_exists('id', $data)) {
            $value = (null === ($data['id'] ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy::$id".')) || \is_scalar(($data['id'] ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy::$id".'))) ? ($data['id'] ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy::$id".')) : throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type(($data['id'] ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy::$id".'))), 'int')));
            try {
                $object->{'id'} = $value;
            } catch (\TypeError $e) {
                throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException($e->getMessage(), 0, $e);
            }
        }
        if (\array_key_exists('name', $data)) {
            $value = (null === ($data['name'] ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy::$name".')) || \is_scalar(($data['name'] ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy::$name".'))) ? ($data['name'] ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy::$name".')) : throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type(($data['name'] ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy::$name".'))), 'string')));
            try {
                $object->{'name'} = $value;
            } catch (\TypeError $e) {
                throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException($e->getMessage(), 0, $e);
            }
        }
        return $object;
    };
    $denormalizers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy|list<int>|null'] = static function ($data) use ($options, $transformers, &$denormalizers) {
        if (\is_array($data)) {
            return $denormalizers['list<int>']($data);
        }
        if ($data instanceof \stdClass) {
            return $denormalizers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy']($data);
        }
        if (null === $data) {
            return null;
        }
        throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy|list<int>|null'));
    };
    $validators['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy|list<int>|null'] = static function ($stream, $offset, $length, $data) use (&$validators) {
        if (\is_array($data)) {
            $validators['list<int>']($stream, $offset, $length, $data);
            return;
        }
        if ($data instanceof \stdClass) {
            $validators['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy']($stream, $offset, $length, $data);
            return;
        }
    };
    $validators['list<int>'] = static function ($stream, $offset, $length, $data) use (&$validators) {
        if (\is_array($data)) {
            foreach (\Symfony\Component\JsonStreamer\Read\Splitter::splitList($stream, $offset, $length) as $k => $v) {
            }
        }
    };
    $validators['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy'] = static function ($stream, $offset, $length, $data) use (&$validators) {
        if ($data instanceof \stdClass) {
            foreach (\Symfony\Component\JsonStreamer\Read\Splitter::splitDict($stream, $offset, $length) as $k => $v) {
            }
        }
    };
    $readers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy|list<int>|null'] = static function ($stream, $offset, $length) use (&$providers, &$denormalizers, &$validators) {
        try {
            $data = \Symfony\Component\JsonStreamer\Read\Decoder::decodeStream($stream, $offset, $length, false);
        } catch (\Symfony\Component\JsonStreamer\Exception\InvalidStreamException $e) {
            if (\JSON_ERROR_INVALID_PROPERTY_NAME !== $e->getPrevious()?->getCode()) {
                throw $e;
            }
            return $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy|list<int>|null']($stream, $offset, $length);
        }
        if (\Symfony\Component\JsonStreamer\Read\Decoder::hasDuplicateKeys($stream, $offset, $length, $data)) {
            $validators['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy|list<int>|null']($stream, $offset, $length, $data);
        }
        return $denormalizers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy|list<int>|null']($data);
    };
    $providers['list<int>'] = static function ($stream, $offset, $length) use ($options, $transformers, $instantiator, &$providers) {
        $data = \Symfony\Component\JsonStreamer\Read\Splitter::splitList($stream, $offset, $length);
        if (null === $data) {
            throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'list<int>'));
        }
        $collection = [];
        foreach ($data as $k => $v) {
            $collection[$k] = \Symfony\Component\JsonStreamer\Read\Decoder::decodeStream($stream, $v[0], $v[1]);
        }
        return $collection;
    };
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy'] = static function ($stream, $offset, $length) use ($options, $transformers, $instantiator, &$providers) {
        $data = \Symfony\Component\JsonStreamer\Read\Splitter::splitDict($stream, $offset, $length);
        if (null === $data) {
            throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy'));
        }
        return $instantiator->instantiate(\Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy::class, static function ($object) use ($stream, $data, $options, $transformers, $instantiator, &$providers) {
            foreach ($data as $k => $v) {
                match ($k) {
                    'id' => $object->id = (\Symfony\Component\JsonStreamer\Read\Decoder::decodeStream($stream, $v[0], $v[1]) ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy::$id".')),
                    'name' => $object->name = (\Symfony\Component\JsonStreamer\Read\Decoder::decodeStream($stream, $v[0], $v[1]) ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy::$name".')),
                    default => \Symfony\Component\JsonStreamer\Read\Decoder::validateStream($stream, $v[0], $v[1]),
                };
            }
        });
    };
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy|list<int>|null'] = static function ($stream, $offset, $length) use ($options, $transformers, $instantiator, &$providers) {
        $data = \Symfony\Component\JsonStreamer\Read\Decoder::decodeStream($stream, $offset, $length);
        if (\is_array($data) && \array_is_list($data)) {
            return $providers['list<int>']($stream, $offset, $length);
        }
        if (\is_array($data)) {
            return $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy']($stream, $offset, $length);
        }
        if (null === $data) {
            return null;
        }
        throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy|list<int>|null'));
    };
    return $providers['list<Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy|list<int>|null>']($stream, 0, null);
};
