<?php

/**
 * @param Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithOtherDummies $data
 */
return static function (mixed $data, \Psr\Container\ContainerInterface $valueTransformers, array $options): \Traversable {
    try {
        $prefix1 = '';
        yield "{{$prefix1}\"name\":";
        yield \json_encode($data->name, \JSON_THROW_ON_ERROR, 511);
        $prefix1 = ',';
        yield "{$prefix1}\"otherDummyOne\":";
        $prefix2 = '';
        yield "{{$prefix2}\"@id\":";
        yield \json_encode($data->otherDummyOne->id, \JSON_THROW_ON_ERROR, 510);
        $prefix2 = ',';
        yield "{$prefix2}\"name\":";
        yield \json_encode($data->otherDummyOne->name, \JSON_THROW_ON_ERROR, 510);
        yield "}{$prefix1}\"otherDummyTwo\":";
        $prefix2 = '';
        yield "{{$prefix2}\"id\":";
        yield \json_encode($data->otherDummyTwo->id, \JSON_THROW_ON_ERROR, 510);
        $prefix2 = ',';
        yield "{$prefix2}\"name\":";
        yield \json_encode($data->otherDummyTwo->name, \JSON_THROW_ON_ERROR, 510);
        yield "}}";
    } catch (\JsonException $e) {
        throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException($e->getMessage(), 0, $e);
    }
};
