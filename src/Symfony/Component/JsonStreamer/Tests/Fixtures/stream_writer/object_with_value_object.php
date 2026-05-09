<?php

/**
 * @param Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithDateTimes $data
 */
return static function (mixed $data, \Psr\Container\ContainerInterface $transformers, array $options): \Traversable {
    try {
        $prefix1 = '';
        yield "{{$prefix1}\"interface\":";
        yield \json_encode($transformers->get('DateTimeInterface')->transform($data->interface, $options), \JSON_THROW_ON_ERROR, 511);
        $prefix1 = ',';
        yield "{$prefix1}\"immutable\":";
        yield \json_encode($transformers->get('DateTimeInterface')->transform($data->immutable, $options), \JSON_THROW_ON_ERROR, 511);
        yield "{$prefix1}\"union\":";
        if ($data->union instanceof \DateTimeImmutable) {
            yield \json_encode($transformers->get('DateTimeInterface')->transform($data->union, $options), \JSON_THROW_ON_ERROR, 511);
        } elseif (\is_int($data->union)) {
            yield \json_encode($data->union, \JSON_THROW_ON_ERROR, 511);
        } else {
            throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value.', \get_debug_type($data->union)));
        }
        yield "}";
    } catch (\JsonException $e) {
        throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException("Cannot encode \"Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithDateTimes\" to JSON: {$e->getMessage()}.", 0, $e);
    }
};
