<?php

/**
 * @param Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNameAttributes $data
 */
return static function (mixed $data, \Psr\Container\ContainerInterface $valueTransformers, array $options): \Traversable {
    try {
        $prefix1 = '';
        yield "{{$prefix1}\"@id\":";
        yield \json_encode($data->id, \JSON_THROW_ON_ERROR, 511);
        $prefix1 = ',';
        yield "{$prefix1}\"name\":";
        yield \json_encode($data->name, \JSON_THROW_ON_ERROR, 511);
        yield "}";
    } catch (\JsonException $e) {
        throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException($e->getMessage(), 0, $e);
    }
};
