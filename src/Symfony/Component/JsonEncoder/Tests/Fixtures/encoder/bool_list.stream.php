<?php

return static function (mixed $data, \Psr\Container\ContainerInterface $normalizers, array $options): \Traversable {
    yield '[';
    $prefix = '';
    foreach ($data as $value) {
        yield $prefix;
        yield $value ? 'true' : 'false';
        $prefix = ',';
    }
    yield ']';
};
