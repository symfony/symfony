<?php

/**
 * @param null $data
 */
return static function (mixed $data, \Psr\Container\ContainerInterface $transformers, array $options): \Traversable {
    try {
        yield "null";
    } catch (\JsonException $e) {
        throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException(\sprintf('Cannot encode "%s" to JSON: %s.', 'null', $e->getMessage()), 0, $e);
    }
};
