<?php

/**
 * @param Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNameAttributes|null $data
 */
return static function (mixed $data, \Psr\Container\ContainerInterface $valueTransformers, array $options): \Traversable {
    try {
        if ($data instanceof \Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNameAttributes) {
            $prefix1 = '';
            yield "{{$prefix1}\"@id\":";
            yield \json_encode($data->id, \JSON_THROW_ON_ERROR, 511);
            $prefix1 = ',';
            yield "{$prefix1}\"name\":";
            yield \json_encode($data->name, \JSON_THROW_ON_ERROR, 511);
            yield "}";
        } elseif (null === $data) {
            yield "null";
        } else {
            throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value.', \get_debug_type($data)));
        }
    } catch (\JsonException $e) {
        throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException($e->getMessage(), 0, $e);
    }
};
