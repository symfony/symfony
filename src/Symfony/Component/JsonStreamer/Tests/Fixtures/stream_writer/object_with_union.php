<?php

return static function (mixed $data, \Psr\Container\ContainerInterface $valueTransformers, array $options): \Traversable {
    try {
        yield '{"value":';
        if ($data->value instanceof \Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum) {
            yield \json_encode($data->value->value, \JSON_THROW_ON_ERROR, 511);
        } elseif (null === $data->value) {
            yield 'null';
        } elseif (\is_string($data->value)) {
            yield \json_encode($data->value, \JSON_THROW_ON_ERROR, 511);
        } else {
            throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value.', \get_debug_type($data->value)));
        }
        yield '}';
    } catch (\JsonException $e) {
        throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException($e->getMessage(), 0, $e);
    }
};
