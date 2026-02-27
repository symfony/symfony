<?php

/**
 * @param Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNestedDictDummies $data
 */
return static function (mixed $data, \Psr\Container\ContainerInterface $valueTransformers, array $options): \Traversable {
    $generators['Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNestedDictDummies'] = static function ($data, $depth) use ($valueTransformers, $options, &$generators) {
        if ($depth >= 512) {
            throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException('Maximum stack depth exceeded');
        }
        $prefix1 = '';
        yield "{{$prefix1}\"dummies\":";
        yield "{";
        $prefix2 = '';
        foreach ($data->dummies as $key1 => $value1) {
            $key1 = \substr(\json_encode($key1), 1, -1);
            yield "{$prefix2}\"{$key1}\":";
            yield from $generators['Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNestedDictDummies']($value1, $depth + 1);
            $prefix2 = ',';
        }
        yield "}}";
    };
    try {
        yield from $generators['Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNestedDictDummies']($data, 0);
    } catch (\JsonException $e) {
        throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException($e->getMessage(), 0, $e);
    }
};
