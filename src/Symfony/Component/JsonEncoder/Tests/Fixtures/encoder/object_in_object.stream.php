<?php

return static function (mixed $data, \Psr\Container\ContainerInterface $normalizers, array $options): \Traversable {
    yield '{"name":';
    yield \json_encode($data->name);
    yield ',"otherDummyOne":{"@id":';
    yield \json_encode($data->otherDummyOne->id);
    yield ',"name":';
    yield \json_encode($data->otherDummyOne->name);
    yield '},"otherDummyTwo":{"id":';
    yield \json_encode($data->otherDummyTwo->id);
    yield ',"name":';
    yield \json_encode($data->otherDummyTwo->name);
    yield '}}';
};
