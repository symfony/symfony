<?php

return static function (string|\Stringable $string, \Psr\Container\ContainerInterface $valueTransformers, \Symfony\Component\JsonStreamer\Read\Instantiator $instantiator, array $options): mixed {
    $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithValueTransformerAttributes'] = static function ($data) use ($options, $valueTransformers, $instantiator, &$providers) {
        return $instantiator->instantiate(\Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithValueTransformerAttributes::class, \array_filter(['id' => $valueTransformers->get('Symfony\Component\JsonStreamer\Tests\Fixtures\ValueTransformer\DivideStringAndCastToIntValueTransformer')->transform($data['id'] ?? '_symfony_missing_value', $options), 'active' => $valueTransformers->get('Symfony\Component\JsonStreamer\Tests\Fixtures\ValueTransformer\StringToBooleanValueTransformer')->transform($data['active'] ?? '_symfony_missing_value', $options), 'name' => strtoupper($data['name'] ?? '_symfony_missing_value'), 'range' => Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithValueTransformerAttributes::explodeRange($data['range'] ?? '_symfony_missing_value', $options)], static function ($v) {
            return '_symfony_missing_value' !== $v;
        }));
    };
    return $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithValueTransformerAttributes'](\Symfony\Component\JsonStreamer\Read\Decoder::decodeString((string) $string));
};
