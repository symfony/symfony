<?php

/**
 * @param list<Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNestedArray> $data
 */
return static function (mixed $data, \Psr\Container\ContainerInterface $valueTransformers, array $options): \Traversable {
    try {
        yield "[";
        $prefix1 = '';
        foreach ($data as $value1) {
            $prefix2 = '';
            yield "{$prefix1}{{$prefix2}\"dummies\":";
            yield "{";
            $prefix3 = '';
            foreach ($value1->dummies as $key2 => $value2) {
                $key2 = is_int($key2) ? $key2 : \substr(\json_encode($key2), 1, -1);
                $prefix4 = '';
                yield "{$prefix3}\"{$key2}\":{{$prefix4}\"dummies\":";
                yield "{";
                $prefix5 = '';
                foreach ($value2->dummies as $key3 => $value3) {
                    $key3 = is_int($key3) ? $key3 : \substr(\json_encode($key3), 1, -1);
                    $prefix6 = '';
                    yield "{$prefix5}\"{$key3}\":{{$prefix6}\"id\":";
                    yield \json_encode($value3->id, \JSON_THROW_ON_ERROR, 506);
                    $prefix6 = ',';
                    yield "{$prefix6}\"name\":";
                    yield \json_encode($value3->name, \JSON_THROW_ON_ERROR, 506);
                    yield "}";
                    $prefix5 = ',';
                }
                $prefix4 = ',';
                yield "}{$prefix4}\"customProperty\":";
                yield \json_encode($value2->customProperty, \JSON_THROW_ON_ERROR, 508);
                yield "}";
                $prefix3 = ',';
            }
            $prefix2 = ',';
            yield "}{$prefix2}\"stringProperty\":";
            yield \json_encode($value1->stringProperty, \JSON_THROW_ON_ERROR, 510);
            yield "}";
            $prefix1 = ',';
        }
        yield "]";
    } catch (\JsonException $e) {
        throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException($e->getMessage(), 0, $e);
    }
};
