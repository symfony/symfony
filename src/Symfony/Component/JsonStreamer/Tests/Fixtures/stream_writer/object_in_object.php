<?php

/**
 * @param Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithOtherDummies $data
 */
return static function (mixed $data, \Psr\Container\ContainerInterface $transformers, array $options): \Traversable {
    try {
        yield "{\"name\":".\json_encode($data->name, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE, 511).",\"otherDummyOne\":{\"@id\":".\json_encode($data->otherDummyOne->id, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE, 510).",\"name\":".\json_encode($data->otherDummyOne->name, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE, 510)."},\"otherDummyTwo\":{\"id\":".\json_encode($data->otherDummyTwo->id, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE, 510).",\"name\":".\json_encode($data->otherDummyTwo->name, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE, 510)."}}";
    } catch (\JsonException $e) {
        throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException('Cannot encode "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithOtherDummies" to JSON: '.$e->getMessage(), 0, $e);
    }
};
