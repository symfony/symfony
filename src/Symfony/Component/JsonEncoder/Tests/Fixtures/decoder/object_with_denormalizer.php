<?php

return static function (string|\Stringable $string, \Psr\Container\ContainerInterface $denormalizers, \Symfony\Component\JsonEncoder\Decode\Instantiator $instantiator, array $options): mixed {
    $providers['Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNormalizerAttributes'] = static function ($data) use ($options, $denormalizers, $instantiator, &$providers) {
        return $instantiator->instantiate(\Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNormalizerAttributes::class, \array_filter(['id' => $denormalizers->get('Symfony\Component\JsonEncoder\Tests\Fixtures\Denormalizer\DivideStringAndCastToIntDenormalizer')->denormalize($data['id'] ?? '_symfony_missing_value', $options), 'active' => $denormalizers->get('Symfony\Component\JsonEncoder\Tests\Fixtures\Denormalizer\BooleanStringDenormalizer')->denormalize($data['active'] ?? '_symfony_missing_value', $options), 'name' => strtoupper($data['name'] ?? '_symfony_missing_value'), 'range' => Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNormalizerAttributes::explodeRange($data['range'] ?? '_symfony_missing_value', $options)], static function ($v) {
            return '_symfony_missing_value' !== $v;
        }));
    };
    return $providers['Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNormalizerAttributes'](\Symfony\Component\JsonEncoder\Decode\NativeDecoder::decodeString((string) $string));
};
