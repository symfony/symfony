<?php

/**
 * @param list<array<string, int>> $data
 */
return static function (mixed $data, \Psr\Container\ContainerInterface $transformers, array $options): \Traversable {
    try {
        $buffer = "[";
        $prefix1 = '';
        foreach ($data as $value1) {
            $buffer .= "{$prefix1}".\json_encode($value1, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_FORCE_OBJECT, 511);
            $prefix1 = ',';
            if (\strlen($buffer) >= 8192) {
                yield $buffer;
                $buffer = '';
            }
        }
        $buffer .= "]";
        if ('' !== $buffer) {
            yield $buffer;
        }
    } catch (\JsonException $e) {
        throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException('Cannot encode "list<array<string, int>>" to JSON: '.$e->getMessage(), 0, $e);
    }
};
