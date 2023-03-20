<?php

return static function (mixed $data, array $config, ?\Psr\Container\ContainerInterface $services): \Traversable {
    yield \json_encode($data);
};
