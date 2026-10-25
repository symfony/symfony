<?php

/**
 * @return Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithValueTransformerAttributes
 */
return static function (string|\Stringable $string, \Psr\Container\ContainerInterface $transformers, \Symfony\Component\JsonStreamer\Read\Instantiator $instantiator, array $options): mixed {
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithValueTransformerAttributes'] = static function ($data) use ($options, $transformers, $instantiator, &$providers) {
        return $instantiator->instantiate(\Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithValueTransformerAttributes::class, [...(isset($data['id']) ? ['id' => $transformers->get('Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Transformer\\DivideStringAndCastToIntValueTransformer')->transform($data['id'], $options)] : []), ...(isset($data['active']) ? ['active' => $transformers->get('Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Transformer\\StringToBooleanValueTransformer')->transform($data['active'], $options)] : []), ...(isset($data['name']) ? ['name' => strtoupper($data['name'])] : []), ...(isset($data['range']) ? ['range' => Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithValueTransformerAttributes::explodeRange($data['range'], $options)] : [])]);
    };
    return $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithValueTransformerAttributes'](\Symfony\Component\JsonStreamer\Read\Decoder::decodeString((string) $string));
};
