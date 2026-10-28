<?php

declare(strict_types=1);

/**
 * @return Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithValueTransformerAttributes
 */
return static function (string|\Stringable $string, \Psr\Container\ContainerInterface $transformers, array $options): mixed {
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithValueTransformerAttributes'] = static function ($data) use ($options, $transformers, &$providers) {
        if (!\is_array($data)) {
            throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithValueTransformerAttributes'));
        }
        $object = new \Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithValueTransformerAttributes();
        if (\array_key_exists('id', $data)) {
            $value = $transformers->get('Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Transformer\\DivideStringAndCastToIntValueTransformer')->transform(($data['id'] ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithValueTransformerAttributes::$id".')), $options);
            try {
                $object->{'id'} = $value;
            } catch (\TypeError $e) {
                throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException($e->getMessage(), 0, $e);
            }
        }
        if (\array_key_exists('active', $data)) {
            $value = $transformers->get('Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Transformer\\StringToBooleanValueTransformer')->transform(($data['active'] ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithValueTransformerAttributes::$active".')), $options);
            try {
                $object->{'active'} = $value;
            } catch (\TypeError $e) {
                throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException($e->getMessage(), 0, $e);
            }
        }
        if (\array_key_exists('name', $data)) {
            $value = strtoupper(($data['name'] ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithValueTransformerAttributes::$name".')));
            try {
                $object->{'name'} = $value;
            } catch (\TypeError $e) {
                throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException($e->getMessage(), 0, $e);
            }
        }
        if (\array_key_exists('range', $data)) {
            $value = Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithValueTransformerAttributes::explodeRange(($data['range'] ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithValueTransformerAttributes::$range".')), $options);
            try {
                $object->{'range'} = $value;
            } catch (\TypeError $e) {
                throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException($e->getMessage(), 0, $e);
            }
        }
        return $object;
    };
    return $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithValueTransformerAttributes'](\Symfony\Component\JsonStreamer\Read\Decoder::decodeString((string) $string));
};
