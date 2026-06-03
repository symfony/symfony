<?php

/**
 * @param bool $data
 */
return static function (mixed $data, \Psr\Container\ContainerInterface $transformers, array $options): \Traversable {
    try {
        yield $data ? 'true' : 'false';
    } catch (\JsonException $e) {
        throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException("Cannot encode \"bool\" to JSON: {$e->getMessage()}.", 0, $e);
    }
};
