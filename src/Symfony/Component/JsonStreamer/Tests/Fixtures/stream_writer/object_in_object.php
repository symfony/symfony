<?php

return static function (mixed $data, \Psr\Container\ContainerInterface $valueTransformers, array $options): \Traversable {
    try {
        yield '{"name":';
        yield \json_encode($data->name, \JSON_THROW_ON_ERROR, 511);
        yield ',"otherDummyOne":{"@id":';
        yield \json_encode($data->otherDummyOne->id, \JSON_THROW_ON_ERROR, 510);
        yield ',"name":';
        yield \json_encode($data->otherDummyOne->name, \JSON_THROW_ON_ERROR, 510);
        yield '},"otherDummyTwo":{"id":';
        yield \json_encode($data->otherDummyTwo->id, \JSON_THROW_ON_ERROR, 510);
        yield ',"name":';
        yield \json_encode($data->otherDummyTwo->name, \JSON_THROW_ON_ERROR, 510);
        yield '}}';
    } catch (\JsonException $e) {
        throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException($e->getMessage(), 0, $e);
    }
};
