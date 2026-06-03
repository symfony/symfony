<?php

/**
 * @param mixed $data
 */
return static function (mixed $data, \Psr\Container\ContainerInterface $transformers, array $options): \Traversable {
    try {
        yield \json_encode($data, \JSON_THROW_ON_ERROR, 512);
    } catch (\JsonException $e) {
        throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException("Cannot encode \"mixed\" to JSON: {$e->getMessage()}.", 0, $e);
    }
};
