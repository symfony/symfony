<?php

return static function (mixed $data, \Psr\Container\ContainerInterface $normalizers, array $options): \Traversable {
    yield '{';
    $prefix = '';
    foreach ($data as $key => $value) {
        $key = \substr(\json_encode($key), 1, -1);
        yield "{$prefix}\"{$key}\":";
        yield \json_encode($value);
        $prefix = ',';
    }
    yield '}';
};
