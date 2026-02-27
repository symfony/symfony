<?php

/**
 * @param Symfony\Component\JsonStreamer\Tests\Fixtures\Model\SelfReferencingDummyList $data
 */
return static function (mixed $data, \Psr\Container\ContainerInterface $valueTransformers, array $options): \Traversable {
    $generators['Symfony\Component\JsonStreamer\Tests\Fixtures\Model\SelfReferencingDummyList'] = static function ($data, $depth) use ($valueTransformers, $options, &$generators) {
        if ($depth >= 512) {
            throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException('Maximum stack depth exceeded');
        }
        $prefix1 = '';
        yield "{{$prefix1}\"items\":";
        yield "{";
        $prefix2 = '';
        foreach ($data->items as $key1 => $value1) {
            $key1 = is_int($key1) ? $key1 : \substr(\json_encode($key1), 1, -1);
            yield "{$prefix2}\"{$key1}\":";
            yield from $generators['Symfony\Component\JsonStreamer\Tests\Fixtures\Model\SelfReferencingDummyList']($value1, $depth + 1);
            $prefix2 = ',';
        }
        yield "}}";
    };
    try {
        yield from $generators['Symfony\Component\JsonStreamer\Tests\Fixtures\Model\SelfReferencingDummyList']($data, 0);
    } catch (\JsonException $e) {
        throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException($e->getMessage(), 0, $e);
    }
};
