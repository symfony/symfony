<?php

/**
 * @param Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithSyntheticProperties $data
 */
return static function (mixed $data, \Psr\Container\ContainerInterface $transformers, array $options): \Traversable {
    try {
        $prefix1 = '';
        yield "{{$prefix1}\"synthetic\":";
        yield \json_encode(Symfony\Component\JsonStreamer\Tests\Fixtures\Mapping\SyntheticPropertyMetadataLoader::true(null, ['_current_object' => $data] + $options), \JSON_THROW_ON_ERROR, 511);
        yield "}";
    } catch (\JsonException $e) {
        throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException("Cannot encode \"Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithSyntheticProperties\" to JSON: {$e->getMessage()}.", 0, $e);
    }
};
