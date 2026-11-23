<?php

/**
 * @param iterable $data
 */
return static function (mixed $data, \Psr\Container\ContainerInterface $transformers, array $options): \Traversable {
    try {
        if (\is_array($data)) {
            yield \json_encode($data, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE, 512);
        } else {
            yield "{";
            $prefix1 = '';
            foreach ($data as $key1 => $value1) {
                $key1 = is_int($key1) ? $key1 : \substr(\json_encode($key1, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE), 1, -1);
                yield "{$prefix1}\"{$key1}\":";
                yield \json_encode($value1, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE, 511);
                $prefix1 = ',';
            }
            yield "}";
        }
    } catch (\JsonException $e) {
        throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException('Cannot encode "iterable" to JSON: '.$e->getMessage(), 0, $e);
    }
};
