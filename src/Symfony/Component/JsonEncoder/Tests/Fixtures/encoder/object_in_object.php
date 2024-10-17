<?php

return static function (mixed $data, \Psr\Container\ContainerInterface $normalizers, array $options): \Traversable {
    yield '{"name":';
    yield \json_encode($data->name);
    yield ',"otherDummyOne":{"@id":';
    yield \json_encode($data->otherDummyOne->id);
    yield ',"name":';
    yield \json_encode($data->otherDummyOne->name);
    yield '},"otherDummyTwo":';
    yield \json_encode($data->otherDummyTwo);
    yield '}';
};
