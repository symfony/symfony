<?php

/**
 * @param bool $data
 */
return static function (mixed $data, \Psr\Container\ContainerInterface $transformers, array $options): \Traversable {
    try {
        yield $data ? 'true' : 'false';
    } catch (\JsonException $e) {
        throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException(\sprintf('Cannot encode "%s" to JSON: %s.', 'bool', $e->getMessage()), 0, $e);
    }
};
