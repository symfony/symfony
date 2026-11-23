<?php

/**
 * @param list<array<string, int>> $data
 */
return static function (mixed $data, \Psr\Container\ContainerInterface $valueTransformers, array $options): \Traversable {
    try {
        yield "[";
        $prefix1 = '';
        foreach ($data as $value1) {
            yield "{$prefix1}";
            yield \json_encode($value1, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_FORCE_OBJECT, 511);
            $prefix1 = ',';
        }
        yield "]";
    } catch (\JsonException $e) {
        throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException($e->getMessage(), 0, $e);
    }
};
