<?php

/**
 * @param list<Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNameAttributes>|null $data
 */
return static function (mixed $data, \Psr\Container\ContainerInterface $transformers, array $options): \Traversable {
    try {
        $buffer = '';
        if (\is_array($data)) {
            $buffer .= "[";
            $prefix1 = '';
            foreach ($data as $value1) {
                $buffer .= "{$prefix1}{\"@id\":".\json_encode($value1->id, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE, 510).",\"name\":".\json_encode($value1->name, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE, 510)."}";
                $prefix1 = ',';
                if (\strlen($buffer) >= 8192) {
                    yield $buffer;
                    $buffer = '';
                }
            }
            $buffer .= "]";
        } elseif (null === $data) {
            $buffer .= "null";
        } else {
            throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value.', \get_debug_type($data)));
        }
        if ('' !== $buffer) {
            yield $buffer;
        }
    } catch (\JsonException $e) {
        throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException('Cannot encode "list<Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithNameAttributes>|null" to JSON: '.$e->getMessage(), 0, $e);
    }
};
