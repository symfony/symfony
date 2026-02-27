<?php

/**
 * @param list<Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNestedList> $data
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
                yield "{$prefix3}{{$prefix4}\"dummies\":";
                yield "[";
                $prefix5 = '';
                foreach ($value2->dummies as $value3) {
                    $prefix6 = '';
                    yield "{$prefix5}{{$prefix6}\"id\":";
                    yield \json_encode($value3->id, \JSON_THROW_ON_ERROR, 506);
                    $prefix6 = ',';
                    yield "{$prefix6}\"name\":";
                    yield \json_encode($value3->name, \JSON_THROW_ON_ERROR, 506);
                    yield "}";
                    $prefix5 = ',';
                }
                $prefix4 = ',';
                yield "]{$prefix4}\"customProperty\":";
                yield \json_encode($value2->customProperty, \JSON_THROW_ON_ERROR, 508);
                yield "}";
                $prefix3 = ',';
            }
            $prefix2 = ',';
            yield "]{$prefix2}\"stringProperty\":";
            yield \json_encode($value1->stringProperty, \JSON_THROW_ON_ERROR, 510);
            yield "}";
            $prefix1 = ',';
        }
        yield "]";
    } catch (\JsonException $e) {
        throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException($e->getMessage(), 0, $e);
    }
};
