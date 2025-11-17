<?php

/**
 * @param Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNameAttributes|int|list<Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum> $data
 */
return static function (mixed $data, \Psr\Container\ContainerInterface $valueTransformers, array $options): \Traversable {
    try {
        if (\is_array($data)) {
            yield "[";
            $prefix1 = '';
            foreach ($data as $value1) {
                yield "{$prefix1}";
                yield \json_encode($value1->value, \JSON_THROW_ON_ERROR, 511);
                $prefix1 = ',';
            }
            yield "]";
        } elseif ($data instanceof \Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNameAttributes) {
            $prefix1 = '';
            yield "{{$prefix1}\"@id\":";
            yield \json_encode($data->id, \JSON_THROW_ON_ERROR, 511);
            $prefix1 = ',';
            yield "{$prefix1}\"name\":";
            yield \json_encode($data->name, \JSON_THROW_ON_ERROR, 511);
            yield "}";
        } elseif (\is_int($data)) {
            yield \json_encode($data, \JSON_THROW_ON_ERROR, 512);
        } else {
            throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value.', \get_debug_type($data)));
        }
    } catch (\JsonException $e) {
        throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException($e->getMessage(), 0, $e);
    }
};
