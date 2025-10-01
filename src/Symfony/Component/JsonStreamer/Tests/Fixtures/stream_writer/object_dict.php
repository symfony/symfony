<?php

/**
 * @param array<string, Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNameAttributes> $data
 */
return static function (mixed $data, \Psr\Container\ContainerInterface $valueTransformers, array $options): \Traversable {
    try {
        yield "{";
        $prefix1 = '';
        foreach ($data as $key1 => $value1) {
            $key1 = \substr(\json_encode($key1), 1, -1);
            $prefix2 = '';
            yield "{$prefix1}\"{$key1}\":{{$prefix2}\"@id\":";
            yield \json_encode($value1->id, \JSON_THROW_ON_ERROR, 510);
            $prefix2 = ',';
            yield "{$prefix2}\"name\":";
            yield \json_encode($value1->name, \JSON_THROW_ON_ERROR, 510);
            yield "}";
            $prefix1 = ',';
        }
        yield "}";
    } catch (\JsonException $e) {
        throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException($e->getMessage(), 0, $e);
    }
};
