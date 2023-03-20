<?php

return static function (mixed $data, array $config, ?\Psr\Container\ContainerInterface $services): \Traversable {
    yield '{"@id":';
    yield \json_encode($data->id);
    yield ',"name":';
    yield \json_encode($data->name);
    yield '}';
};
