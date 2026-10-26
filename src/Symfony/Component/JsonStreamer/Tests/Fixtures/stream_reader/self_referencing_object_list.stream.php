<?php

/**
 * @return Symfony\Component\JsonStreamer\Tests\Fixtures\Model\SelfReferencingDummyList
 */
return static function (mixed $stream, \Psr\Container\ContainerInterface $transformers, \Symfony\Component\JsonStreamer\Read\LazyInstantiator $instantiator, array $options): mixed {
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\SelfReferencingDummyList'] = static function ($stream, $offset, $length) use ($options, $transformers, $instantiator, &$providers) {
        $data = \Symfony\Component\JsonStreamer\Read\Splitter::splitDict($stream, $offset, $length);
        if (null === $data) {
            throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\SelfReferencingDummyList'));
        }
        return $instantiator->instantiate(\Symfony\Component\JsonStreamer\Tests\Fixtures\Model\SelfReferencingDummyList::class, static function ($object) use ($stream, $data, $options, $transformers, $instantiator, &$providers) {
            foreach ($data as $k => $v) {
                match ($k) {
                    'items' => $object->items = (\Symfony\Component\JsonStreamer\Read\Decoder::isNull($stream, $v[0]) ? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\SelfReferencingDummyList::$items".') : $providers['array<int|string, Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\SelfReferencingDummyList>']($stream, $v[0], $v[1])),
                    default => \Symfony\Component\JsonStreamer\Read\Decoder::validateStream($stream, $v[0], $v[1]),
                };
            }
        });
    };
    $providers['array<int|string, Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\SelfReferencingDummyList>'] = static function ($stream, $offset, $length) use ($options, $transformers, $instantiator, &$providers, &$readers) {
        $data = \Symfony\Component\JsonStreamer\Read\Splitter::splitCollection($stream, $offset, $length);
        if (null === $data) {
            throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'array<int|string, Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\SelfReferencingDummyList>'));
        }
        $collection = [];
        foreach ($data as $k => $v) {
            $collection[$k] = $readers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\SelfReferencingDummyList']($stream, $v[0], $v[1]);
        }
        return $collection;
    };
    $denormalizers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\SelfReferencingDummyList'] = static function ($data) use ($options, $transformers, &$denormalizers) {
        if (!$data instanceof \stdClass) {
            throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\SelfReferencingDummyList'));
        }
        $data = (array) $data;
        $object = (new \ReflectionClass(\Symfony\Component\JsonStreamer\Tests\Fixtures\Model\SelfReferencingDummyList::class))->newInstanceWithoutConstructor();
        if (\array_key_exists('items', $data)) {
            $value = $denormalizers['array<int|string, Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\SelfReferencingDummyList>'](($data['items'] ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\SelfReferencingDummyList::$items".')));
            try {
                $object->{'items'} = $value;
            } catch (\TypeError $e) {
                throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException($e->getMessage(), 0, $e);
            }
        }
        return $object;
    };
    $denormalizers['array<int|string, Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\SelfReferencingDummyList>'] = static function ($data) use ($options, $transformers, &$denormalizers) {
        if (!(\is_array($data) || $data instanceof \stdClass)) {
            throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'array<int|string, Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\SelfReferencingDummyList>'));
        }
        $collection = [];
        foreach ($data as $k => $v) {
            $collection[$k] = $denormalizers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\SelfReferencingDummyList']($v);
        }
        return $collection;
    };
    $validators['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\SelfReferencingDummyList'] = static function ($stream, $offset, $length, $data) use (&$validators) {
        if ($data instanceof \stdClass) {
            foreach (\Symfony\Component\JsonStreamer\Read\Splitter::splitDict($stream, $offset, $length) as $k => $v) {
                match ($k) {
                    'items' => $validators['array<int|string, Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\SelfReferencingDummyList>']($stream, $v[0], $v[1], $data->{$k} ?? null),
                    default => null,
                };
            }
        }
    };
    $validators['array<int|string, Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\SelfReferencingDummyList>'] = static function ($stream, $offset, $length, $data) use (&$validators) {
        if (\is_array($data) || $data instanceof \stdClass) {
            foreach (\Symfony\Component\JsonStreamer\Read\Splitter::splitCollection($stream, $offset, $length) as $k => $v) {
                $validators['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\SelfReferencingDummyList']($stream, $v[0], $v[1], \is_array($data) ? ($data[$k] ?? null) : ($data->{$k} ?? null));
            }
        }
    };
    $readers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\SelfReferencingDummyList'] = static function ($stream, $offset, $length) use (&$providers, &$denormalizers, &$validators) {
        try {
            $data = \Symfony\Component\JsonStreamer\Read\Decoder::decodeStream($stream, $offset, $length, false);
        } catch (\Symfony\Component\JsonStreamer\Exception\InvalidStreamException $e) {
            if (\JSON_ERROR_INVALID_PROPERTY_NAME !== $e->getPrevious()?->getCode()) {
                throw $e;
            }
            return $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\SelfReferencingDummyList']($stream, $offset, $length);
        }
        if (\Symfony\Component\JsonStreamer\Read\Decoder::hasDuplicateKeys($stream, $offset, $length, $data)) {
            $validators['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\SelfReferencingDummyList']($stream, $offset, $length, $data);
        }
        return $denormalizers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\SelfReferencingDummyList']($data);
    };
    return $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\SelfReferencingDummyList']($stream, 0, null);
};
