<?php

declare(strict_types=1);

/**
 * @return Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithValueTransformerAttributes
 */
return static function (mixed $stream, \Psr\Container\ContainerInterface $valueTransformers, \Symfony\Component\JsonStreamer\Read\LazyInstantiator $instantiator, array $options): mixed {
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithValueTransformerAttributes'] = static function ($stream, $offset, $length) use ($options, $valueTransformers, $instantiator, &$providers) {
        $data = \Symfony\Component\JsonStreamer\Read\Splitter::splitDict($stream, $offset, $length);
        if (null === $data) {
            throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithValueTransformerAttributes'));
        }
        return $instantiator->instantiate(\Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithValueTransformerAttributes::class, static function ($object) use ($stream, $data, $options, $valueTransformers, $instantiator, &$providers) {
            $properties = [];
            foreach ($data as $k => $v) {
                match ($k) {
                    'id' => $properties['id'] = $valueTransformers->get('Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\ValueTransformer\\DivideStringAndCastToIntValueTransformer')->transform((\Symfony\Component\JsonStreamer\Read\Decoder::decodeStream($stream, $v[0], $v[1]) ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithValueTransformerAttributes::$id".')), $options),
                    'active' => $properties['active'] = $valueTransformers->get('Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\ValueTransformer\\StringToBooleanValueTransformer')->transform((\Symfony\Component\JsonStreamer\Read\Decoder::decodeStream($stream, $v[0], $v[1]) ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithValueTransformerAttributes::$active".')), $options),
                    'name' => $properties['name'] = strtoupper((\Symfony\Component\JsonStreamer\Read\Decoder::decodeStream($stream, $v[0], $v[1]) ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithValueTransformerAttributes::$name".'))),
                    'range' => $properties['range'] = Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithValueTransformerAttributes::explodeRange((\Symfony\Component\JsonStreamer\Read\Decoder::decodeStream($stream, $v[0], $v[1]) ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithValueTransformerAttributes::$range".')), $options),
                    default => null,
                };
            }
            foreach ($properties as $name => $value) {
                try {
                    $object->{$name} = $value;
                } catch (\TypeError $e) {
                    throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException($e->getMessage(), 0, $e);
                }
            }
        });
    };
    return $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithValueTransformerAttributes']($stream, 0, null);
};
