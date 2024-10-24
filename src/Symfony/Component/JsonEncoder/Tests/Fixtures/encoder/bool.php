<?php

return static function (mixed $data, \Psr\Container\ContainerInterface $normalizers, array $options): \Traversable {
    yield $data ? 'true' : 'false';
};
