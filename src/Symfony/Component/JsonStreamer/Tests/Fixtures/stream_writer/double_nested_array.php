<?php

/**
 * @param list<Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNestedArray> $data
 */
return static function (mixed $data, \Psr\Container\ContainerInterface $transformers, array $options): \Traversable {
    try {
        $buffer = "[";
        $prefix1 = '';
        foreach ($data as $value1) {
            $buffer .= "{$prefix1}{\"dummies\":{";
            $prefix3 = '';
            foreach ($value1->dummies as $key2 => $value2) {
                $key2 = is_int($key2) ? $key2 : \substr(\json_encode($key2, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE), 1, -1);
                $buffer .= "{$prefix3}\"{$key2}\":{\"dummies\":{";
                $prefix5 = '';
                foreach ($value2->dummies as $key3 => $value3) {
                    $key3 = is_int($key3) ? $key3 : \substr(\json_encode($key3, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE), 1, -1);
                    $buffer .= "{$prefix5}\"{$key3}\":{\"id\":".\json_encode($value3->id, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE, 506).",\"name\":".\json_encode($value3->name, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE, 506)."}";
                    $prefix5 = ',';
                    if (\strlen($buffer) >= 8192) {
                        yield $buffer;
                        $buffer = '';
                    }
                }
                $buffer .= "},\"customProperty\":".\json_encode($value2->customProperty, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE, 508)."}";
                $prefix3 = ',';
                if (\strlen($buffer) >= 8192) {
                    yield $buffer;
                    $buffer = '';
                }
            }
            $buffer .= "},\"stringProperty\":".\json_encode($value1->stringProperty, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE, 510)."}";
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
        throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException('Cannot encode "list<Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithNestedArray>" to JSON: '.$e->getMessage(), 0, $e);
    }
};
