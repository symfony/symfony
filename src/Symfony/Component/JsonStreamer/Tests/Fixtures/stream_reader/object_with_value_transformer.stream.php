<?php

/**
 * @return Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithValueTransformerAttributes
 */
return static function (mixed $stream, \Psr\Container\ContainerInterface $transformers, \Symfony\Component\JsonStreamer\Read\LazyInstantiator $instantiator, array $options): mixed {
    $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithValueTransformerAttributes'] = static function ($stream, $offset, $length) use ($options, $transformers, $instantiator, &$providers) {
        $data = \Symfony\Component\JsonStreamer\Read\Splitter::splitDict($stream, $offset, $length);
        return $instantiator->instantiate(\Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithValueTransformerAttributes::class, static function ($object) use ($stream, $data, $options, $transformers, $instantiator, &$providers) {
            foreach ($data as $k => $v) {
                match ($k) {
                    'id' => $object->id = $transformers->get('Symfony\Component\JsonStreamer\Tests\Fixtures\Transformer\DivideStringAndCastToIntValueTransformer')->transform(\Symfony\Component\JsonStreamer\Read\Decoder::decodeStream($stream, $v[0], $v[1]), $options),
                    'active' => $object->active = $transformers->get('Symfony\Component\JsonStreamer\Tests\Fixtures\Transformer\StringToBooleanValueTransformer')->transform(\Symfony\Component\JsonStreamer\Read\Decoder::decodeStream($stream, $v[0], $v[1]), $options),
                    'name' => $object->name = strtoupper(\Symfony\Component\JsonStreamer\Read\Decoder::decodeStream($stream, $v[0], $v[1])),
                    'range' => $object->range = Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithValueTransformerAttributes::explodeRange(\Symfony\Component\JsonStreamer\Read\Decoder::decodeStream($stream, $v[0], $v[1]), $options),
                    default => null,
                };
            }
        });
    };
    return $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithValueTransformerAttributes']($stream, 0, null);
};
