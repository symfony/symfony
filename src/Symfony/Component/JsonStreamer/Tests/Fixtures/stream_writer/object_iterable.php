<?php

return static function (mixed $data, \Psr\Container\ContainerInterface $valueTransformers, array $options): \Traversable {
    try {
        yield '{';
        $prefix = '';
        foreach ($data as $key => $value) {
            $key = is_int($key) ? $key : \substr(\json_encode($key), 1, -1);
            yield "{$prefix}\"{$key}\":";
            yield '{"id":';
            yield \json_encode($value->id, \JSON_THROW_ON_ERROR, 510);
            yield ',"name":';
            yield \json_encode($value->name, \JSON_THROW_ON_ERROR, 510);
            yield '}';
            $prefix = ',';
        }
        yield '}';
    } catch (\JsonException $e) {
        throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException($e->getMessage(), 0, $e);
    }
};
