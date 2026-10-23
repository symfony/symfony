<?php

/**
 * @param Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithMixedNullableProperties $data
 */
return static function (mixed $data, \Psr\Container\ContainerInterface $transformers, array $options): \Traversable {
    try {
        $buffer = "{";
        $prefix1 = '';
        if (null === $data->first && ($options['include_null_properties'] ?? false)) {
            $buffer .= "{$prefix1}\"first\":null";
            $prefix1 = ',';
        }
        if (null !== $data->first) {
            $buffer .= "{$prefix1}\"first\":".\json_encode($data->first, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE, 511);
            $prefix1 = ',';
        }
        $buffer .= "{$prefix1}\"id\":".\json_encode($data->id, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE, 511);
        if (null === $data->last && ($options['include_null_properties'] ?? false)) {
            $buffer .= ",\"last\":null";
        }
        if (null !== $data->last) {
            $buffer .= ",\"last\":".\json_encode($data->last, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE, 511);
        }
        $buffer .= "}";
        if ('' !== $buffer) {
            yield $buffer;
        }
    } catch (\JsonException $e) {
        throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException('Cannot encode "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithMixedNullableProperties" to JSON: '.$e->getMessage(), 0, $e);
    }
};
