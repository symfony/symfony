<?php

/**
 * @param list<Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithList> $data
 */
return static function (mixed $data, \Psr\Container\ContainerInterface $valueTransformers, array $options): \Traversable {
    try {
        yield "[";
        $prefix1 = '';
        foreach ($data as $value1) {
            $prefix2 = '';
            yield "{$prefix1}{{$prefix2}\"dummies\":";
            yield "[";
            $prefix3 = '';
            foreach ($value1->dummies as $value2) {
                $prefix4 = '';
                yield "{$prefix3}{{$prefix4}\"id\":";
                yield \json_encode($value2->id, \JSON_THROW_ON_ERROR, 508);
                $prefix4 = ',';
                yield "{$prefix4}\"name\":";
                yield \json_encode($value2->name, \JSON_THROW_ON_ERROR, 508);
                yield "}";
                $prefix3 = ',';
            }
            $prefix2 = ',';
            yield "]{$prefix2}\"customProperty\":";
            yield \json_encode($value1->customProperty, \JSON_THROW_ON_ERROR, 510);
            yield "}";
            $prefix1 = ',';
        }
        yield "]";
    } catch (\JsonException $e) {
        throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException($e->getMessage(), 0, $e);
    }
};
