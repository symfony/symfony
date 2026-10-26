<?php

/**
 * @return Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithValueTransformerAttributes
 */
return static function (string|\Stringable $string, \Psr\Container\ContainerInterface $valueTransformers, \Symfony\Component\JsonStreamer\Read\Instantiator $instantiator, array $options): mixed {
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithValueTransformerAttributes'] = static function ($data) use ($options, $valueTransformers, $instantiator, &$providers) {
        if (!\is_array($data)) {
            throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithValueTransformerAttributes'));
        }
        return $instantiator->instantiate(\Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithValueTransformerAttributes::class, [...(\array_key_exists('id', $data) ? ['id' => $valueTransformers->get('Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\ValueTransformer\\DivideStringAndCastToIntValueTransformer')->transform(($data['id'] ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithValueTransformerAttributes::$id".')), $options)] : []), ...(\array_key_exists('active', $data) ? ['active' => $valueTransformers->get('Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\ValueTransformer\\StringToBooleanValueTransformer')->transform(($data['active'] ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithValueTransformerAttributes::$active".')), $options)] : []), ...(\array_key_exists('name', $data) ? ['name' => strtoupper(($data['name'] ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithValueTransformerAttributes::$name".')))] : []), ...(\array_key_exists('range', $data) ? ['range' => Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithValueTransformerAttributes::explodeRange(($data['range'] ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithValueTransformerAttributes::$range".')), $options)] : [])]);
    };
    return $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithValueTransformerAttributes'](\Symfony\Component\JsonStreamer\Read\Decoder::decodeString((string) $string));
};
