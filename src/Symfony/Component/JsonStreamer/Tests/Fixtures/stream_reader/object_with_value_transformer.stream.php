<?php

return static function (mixed $stream, \Psr\Container\ContainerInterface $valueTransformers, \Symfony\Component\JsonStreamer\Read\LazyInstantiator $instantiator, array $options): mixed {
    $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithValueTransformerAttributes'] = static function ($stream, $offset, $length) use ($options, $valueTransformers, $instantiator, &$providers) {
        $data = \Symfony\Component\JsonStreamer\Read\Splitter::splitDict($stream, $offset, $length);
        return $instantiator->instantiate(\Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithValueTransformerAttributes::class, static function ($object) use ($stream, $data, $options, $valueTransformers, $instantiator, &$providers) {
            foreach ($data as $k => $v) {
                match ($k) {
                    'id' => $object->id = $valueTransformers->get('Symfony\Component\JsonStreamer\Tests\Fixtures\ValueTransformer\DivideStringAndCastToIntValueTransformer')->transform(\Symfony\Component\JsonStreamer\Read\Decoder::decodeStream($stream, $v[0], $v[1]), $options),
                    'active' => $object->active = $valueTransformers->get('Symfony\Component\JsonStreamer\Tests\Fixtures\ValueTransformer\StringToBooleanValueTransformer')->transform(\Symfony\Component\JsonStreamer\Read\Decoder::decodeStream($stream, $v[0], $v[1]), $options),
                    'name' => $object->name = strtoupper(\Symfony\Component\JsonStreamer\Read\Decoder::decodeStream($stream, $v[0], $v[1])),
                    'range' => $object->range = Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithValueTransformerAttributes::explodeRange(\Symfony\Component\JsonStreamer\Read\Decoder::decodeStream($stream, $v[0], $v[1]), $options),
                    default => null,
                };
            }
        });
    };
    return $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithValueTransformerAttributes']($stream, 0, null);
};
