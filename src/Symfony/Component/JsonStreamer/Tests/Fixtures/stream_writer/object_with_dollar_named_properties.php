<?php

/**
 * @param Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithDollarNamedProperties $data
 */
return static function (mixed $data, \Psr\Container\ContainerInterface $valueTransformers, array $options): \Traversable {
    try {
        $prefix1 = '';
        yield "{{$prefix1}\"\$foo\":";
        yield $data->foo ? 'true' : 'false';
        $prefix1 = ',';
        yield "{$prefix1}\"{\$foo->bar}\":";
        yield $data->bar ? 'true' : 'false';
        yield "}";
    } catch (\JsonException $e) {
        throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException($e->getMessage(), 0, $e);
    }
};
