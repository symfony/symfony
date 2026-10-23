<?php

/**
 * @param Symfony\Component\JsonStreamer\Tests\Fixtures\Model\SelfReferencingDummyWithCollections $data
 */
return static function (mixed $data, \Psr\Container\ContainerInterface $valueTransformers, array $options): \Traversable {
    $generators['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\SelfReferencingDummyWithCollections'] = static function ($data, $depth) use ($valueTransformers, $options, &$generators) {
        if ($depth >= 512) {
            throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException('Maximum stack depth exceeded');
        }
        $prefix1 = '';
        yield "{";
        if (null === $data->self && ($options['include_null_properties'] ?? false)) {
            yield "{$prefix1}\"self\":null";
            $prefix1 = ',';
        }
        if (null !== $data->self) {
            yield "{$prefix1}\"self\":";
            yield from $generators['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\SelfReferencingDummyWithCollections']($data->self, $depth + 1);
            $prefix1 = ',';
        }
        yield "{$prefix1}\"mixed\":";
        $encoded = \json_encode($data->mixed, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE, \max(1, 511 - $depth));
        if ($depth >= 511 && ('[' === $encoded[0] || '{' === $encoded[0])) {
            throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException('Maximum stack depth exceeded');
        }
        yield $encoded;
        unset($encoded);
        $prefix1 = ',';
        if (null === $data->dict && ($options['include_null_properties'] ?? false)) {
            yield "{$prefix1}\"dict\":null";
        }
        if (null !== $data->dict) {
            yield "{$prefix1}\"dict\":";
            $encoded = \json_encode($data->dict, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE, \max(1, 511 - $depth));
            if ($depth >= 511 && ('[' === $encoded[0] || '{' === $encoded[0])) {
                throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException('Maximum stack depth exceeded');
            }
            yield $encoded;
            unset($encoded);
        }
        if (null === $data->listOfDicts && ($options['include_null_properties'] ?? false)) {
            yield "{$prefix1}\"listOfDicts\":null";
        }
        if (null !== $data->listOfDicts) {
            yield "{$prefix1}\"listOfDicts\":";
            if ($depth + 1 >= 512) {
                throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException('Maximum stack depth exceeded');
            }
            yield "[";
            $prefix2 = '';
            foreach ($data->listOfDicts as $value1) {
                $encoded = \json_encode($value1, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_FORCE_OBJECT, \max(1, 510 - $depth));
                if ($depth >= 510 && ('[' === $encoded[0] || '{' === $encoded[0])) {
                    throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException('Maximum stack depth exceeded');
                }
                yield "{$prefix2}";
                yield $encoded;
                unset($encoded);
                $prefix2 = ',';
            }
            yield "]";
        }
        if (null === $data->listsOfObjects && ($options['include_null_properties'] ?? false)) {
            yield "{$prefix1}\"listsOfObjects\":null";
        }
        if (null !== $data->listsOfObjects) {
            yield "{$prefix1}\"listsOfObjects\":";
            if ($depth + 1 >= 512) {
                throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException('Maximum stack depth exceeded');
            }
            yield "[";
            $prefix2 = '';
            foreach ($data->listsOfObjects as $value1) {
                if ($depth + 2 >= 512) {
                    throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException('Maximum stack depth exceeded');
                }
                yield "{$prefix2}[";
                $prefix3 = '';
                foreach ($value1 as $value2) {
                    if ($depth + 3 >= 512) {
                        throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException('Maximum stack depth exceeded');
                    }
                    $prefix4 = '';
                    yield "{$prefix3}{{$prefix4}\"id\":";
                    yield \json_encode($value2->id, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE, 508);
                    $prefix4 = ',';
                    yield "{$prefix4}\"name\":";
                    yield \json_encode($value2->name, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE, 508);
                    yield "}";
                    $prefix3 = ',';
                }
                yield "]";
                $prefix2 = ',';
            }
            yield "]";
        }
        yield "}";
    };
    try {
        yield from $generators['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\SelfReferencingDummyWithCollections']($data, 0);
    } catch (\JsonException $e) {
        throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException($e->getMessage(), 0, $e);
    }
};
