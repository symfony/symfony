<?php

/**
 * @param list<Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNestedList> $data
 */
return static function (mixed $data, \Psr\Container\ContainerInterface $transformers, array $options): \Traversable {
    try {
        $buffer = "[";
        $prefix1 = '';
        foreach ($data as $value1) {
            $buffer .= "{$prefix1}{\"dummies\":[";
            $prefix3 = '';
            foreach ($value1->dummies as $value2) {
                $buffer .= "{$prefix3}{\"dummies\":[";
                $prefix5 = '';
                foreach ($value2->dummies as $value3) {
                    $buffer .= "{$prefix5}{\"id\":".\json_encode($value3->id, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE, 506).",\"name\":".\json_encode($value3->name, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE, 506)."}";
                    $prefix5 = ',';
                    if (\strlen($buffer) >= 8192) {
                        yield $buffer;
                        $buffer = '';
                    }
                }
                $buffer .= "],\"customProperty\":".\json_encode($value2->customProperty, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE, 508)."}";
                $prefix3 = ',';
                if (\strlen($buffer) >= 8192) {
                    yield $buffer;
                    $buffer = '';
                }
            }
            $buffer .= "],\"stringProperty\":".\json_encode($value1->stringProperty, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE, 510)."}";
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
        throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException('Cannot encode "list<Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithNestedList>" to JSON: '.$e->getMessage(), 0, $e);
    }
};
