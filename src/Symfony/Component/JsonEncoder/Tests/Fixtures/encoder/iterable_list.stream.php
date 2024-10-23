<?php

return static function (mixed $data, \Psr\Container\ContainerInterface $normalizers, array $options): \Traversable {
    yield '[';
    $prefix = '';
    foreach ($data as $value) {
        yield $prefix;
        yield \json_encode($value);
        $prefix = ',';
    }
    yield ']';
};
