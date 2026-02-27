<?php

/**
 * @param Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum $data
 */
return static function (mixed $data, \Psr\Container\ContainerInterface $valueTransformers, array $options): \Traversable {
    try {
        yield \json_encode($data->value, \JSON_THROW_ON_ERROR, 512);
    } catch (\JsonException $e) {
        throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException($e->getMessage(), 0, $e);
    }
};
