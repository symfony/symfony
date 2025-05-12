<?php

return static function (mixed $data, \Psr\Container\ContainerInterface $valueTransformers, array $options): \Traversable {
    $generators['Symfony\Component\JsonStreamer\Tests\Fixtures\Model\SelfReferencingDummy'] = static function ($data, $depth) use ($valueTransformers, $options, &$generators) {
        if ($depth >= 512) {
            throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException('Maximum stack depth exceeded');
        }
        yield '{"@self":';
        if ($data->self instanceof \Symfony\Component\JsonStreamer\Tests\Fixtures\Model\SelfReferencingDummy) {
            yield from $generators['Symfony\Component\JsonStreamer\Tests\Fixtures\Model\SelfReferencingDummy']($data->self, $depth + 1);
        } elseif (null === $data->self) {
            yield 'null';
        } else {
            throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value.', \get_debug_type($data->self)));
        }
        yield '}';
    };
    try {
        yield from $generators['Symfony\Component\JsonStreamer\Tests\Fixtures\Model\SelfReferencingDummy']($data, 0);
    } catch (\JsonException $e) {
        throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException($e->getMessage(), 0, $e);
    }
};
