<?php

/**
 * @param Symfony\Component\JsonStreamer\Tests\Fixtures\Model\SelfReferencingDummy $data
 */
return static function (mixed $data, \Psr\Container\ContainerInterface $valueTransformers, array $options): \Traversable {
    $generators['Symfony\Component\JsonStreamer\Tests\Fixtures\Model\SelfReferencingDummy'] = static function ($data, $depth) use ($valueTransformers, $options, &$generators) {
        if ($depth >= 512) {
            throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException('Maximum stack depth exceeded');
        }
        $prefix1 = '';
        yield "{";
        if (null === $data->self && ($options['include_null_properties'] ?? false)) {
            yield "{$prefix1}\"@self\":null";
        }
        if (null !== $data->self) {
            yield "{$prefix1}\"@self\":";
            yield from $generators['Symfony\Component\JsonStreamer\Tests\Fixtures\Model\SelfReferencingDummy']($data->self, $depth + 1);
        }
        yield "}";
    };
    try {
        yield from $generators['Symfony\Component\JsonStreamer\Tests\Fixtures\Model\SelfReferencingDummy']($data, 0);
    } catch (\JsonException $e) {
        throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException($e->getMessage(), 0, $e);
    }
};
