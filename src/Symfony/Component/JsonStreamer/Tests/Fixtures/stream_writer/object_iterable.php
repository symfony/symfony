<?php

/**
 * @param iterable<int|string, Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy> $data
 */
return static function (mixed $data, \Psr\Container\ContainerInterface $transformers, array $options): \Traversable {
    try {
        $buffer = "{";
        $prefix1 = '';
        foreach ($data as $key1 => $value1) {
            $key1 = is_int($key1) ? $key1 : \substr(\json_encode($key1, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE), 1, -1);
            $buffer .= "{$prefix1}\"{$key1}\":{\"id\":".\json_encode($value1->id, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE, 510).",\"name\":".\json_encode($value1->name, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE, 510)."}";
            $prefix1 = ',';
            if (\strlen($buffer) >= 8192) {
                yield $buffer;
                $buffer = '';
            }
        }
        $buffer .= "}";
        if ('' !== $buffer) {
            yield $buffer;
        }
    } catch (\JsonException $e) {
        throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException('Cannot encode "iterable<int|string, Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy>" to JSON: '.$e->getMessage(), 0, $e);
    }
};
