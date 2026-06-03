<?php

/**
 * @param Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum $data
 */
return static function (mixed $data, \Psr\Container\ContainerInterface $transformers, array $options): \Traversable {
    try {
        yield \json_encode($data->value, \JSON_THROW_ON_ERROR, 512);
    } catch (\JsonException $e) {
        throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException("Cannot encode \"Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum\" to JSON: {$e->getMessage()}.", 0, $e);
    }
};
