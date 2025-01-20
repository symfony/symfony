<?php

return static function (mixed $data, \Psr\Container\ContainerInterface $normalizers, array $options): \Traversable {
    yield '[';
    $prefix = '';
    foreach ($data as $value) {
        yield $prefix;
        yield '{"@id":';
        yield \json_encode($value->id);
        yield ',"name":';
        yield \json_encode($value->name);
        yield '}';
        $prefix = ',';
    }
    yield ']';
};
