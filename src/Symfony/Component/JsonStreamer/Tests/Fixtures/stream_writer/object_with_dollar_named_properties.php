<?php

/**
 * @param Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithDollarNamedProperties $data
 */
return static function (mixed $data, \Psr\Container\ContainerInterface $transformers, array $options): \Traversable {
    try {
        yield "{\"\$foo\":".($data->foo ? 'true' : 'false').",\"{\$foo->bar}\":".($data->bar ? 'true' : 'false')."}";
    } catch (\JsonException $e) {
        throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException('Cannot encode "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithDollarNamedProperties" to JSON: '.$e->getMessage(), 0, $e);
    }
};
