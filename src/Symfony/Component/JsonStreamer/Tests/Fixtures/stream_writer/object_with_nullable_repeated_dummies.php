<?php

/**
 * @param Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNullableRepeatedDummies $data
 */
return static function (mixed $data, \Psr\Container\ContainerInterface $transformers, array $options): \Traversable {
    $generators['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy'] = static function ($data, $depth) use ($transformers, $options, &$generators) {
        if ($depth >= 512) {
            throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException('Maximum stack depth exceeded');
        }
        $prefix1 = '';
        yield "{{$prefix1}\"id\":";
        yield \json_encode($data->id, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE, 511);
        $prefix1 = ',';
        yield "{$prefix1}\"name\":";
        yield \json_encode($data->name, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE, 511);
        yield "}";
    };
    try {
        $prefix1 = '';
        yield "{";
        if (null === $data->first && ($options['include_null_properties'] ?? false)) {
            yield "{$prefix1}\"first\":null";
            $prefix1 = ',';
        }
        if (null !== $data->first) {
            yield "{$prefix1}\"first\":";
            yield from $generators['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy']($data->first, 1);
            $prefix1 = ',';
        }
        if (null === $data->second && ($options['include_null_properties'] ?? false)) {
            yield "{$prefix1}\"second\":null";
        }
        if (null !== $data->second) {
            yield "{$prefix1}\"second\":";
            yield from $generators['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy']($data->second, 1);
        }
        yield "}";
    } catch (\JsonException $e) {
        throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException('Cannot encode "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithNullableRepeatedDummies" to JSON: '.$e->getMessage(), 0, $e);
    }
};
