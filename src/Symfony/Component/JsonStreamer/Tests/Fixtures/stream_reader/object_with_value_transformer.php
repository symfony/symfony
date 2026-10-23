<?php

/**
 * @return Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithValueTransformerAttributes
 */
return static function (string|\Stringable $string, \Psr\Container\ContainerInterface $transformers, \Symfony\Component\JsonStreamer\Read\Instantiator $instantiator, array $options): mixed {
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithValueTransformerAttributes'] = static function ($data) use ($options, $transformers, $instantiator, &$providers) {
        return $instantiator->instantiate(\Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithValueTransformerAttributes::class, \array_filter(['id' => isset($data['id']) ? $transformers->get('Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Transformer\\DivideStringAndCastToIntValueTransformer')->transform($data['id'], $options) : '_symfony_missing_value', 'active' => isset($data['active']) ? $transformers->get('Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Transformer\\StringToBooleanValueTransformer')->transform($data['active'], $options) : '_symfony_missing_value', 'name' => isset($data['name']) ? strtoupper($data['name']) : '_symfony_missing_value', 'range' => isset($data['range']) ? Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithValueTransformerAttributes::explodeRange($data['range'], $options) : '_symfony_missing_value'], static function ($v) {
            return '_symfony_missing_value' !== $v;
        }));
    };
    return $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithValueTransformerAttributes'](\Symfony\Component\JsonStreamer\Read\Decoder::decodeString((string) $string));
};
