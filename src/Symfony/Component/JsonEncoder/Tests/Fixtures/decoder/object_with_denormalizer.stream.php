<?php

return static function (mixed $stream, \Psr\Container\ContainerInterface $denormalizers, \Symfony\Component\JsonEncoder\Decode\LazyInstantiator $instantiator, array $options): mixed {
    $providers['Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNormalizerAttributes'] = static function ($stream, $offset, $length) use ($options, $denormalizers, $instantiator, &$providers) {
        $data = \Symfony\Component\JsonEncoder\Decode\Splitter::splitDict($stream, $offset, $length);
        return $instantiator->instantiate(\Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNormalizerAttributes::class, static function ($object) use ($stream, $data, $options, $denormalizers, $instantiator, &$providers) {
            foreach ($data as $k => $v) {
                match ($k) {
                    'id' => $object->id = $denormalizers->get('Symfony\Component\JsonEncoder\Tests\Fixtures\Denormalizer\DivideStringAndCastToIntDenormalizer')->denormalize(\Symfony\Component\JsonEncoder\Decode\NativeDecoder::decodeStream($stream, $v[0], $v[1]), $options),
                    'active' => $object->active = $denormalizers->get('Symfony\Component\JsonEncoder\Tests\Fixtures\Denormalizer\BooleanStringDenormalizer')->denormalize(\Symfony\Component\JsonEncoder\Decode\NativeDecoder::decodeStream($stream, $v[0], $v[1]), $options),
                    'name' => $object->name = strtoupper(\Symfony\Component\JsonEncoder\Decode\NativeDecoder::decodeStream($stream, $v[0], $v[1])),
                    'range' => $object->range = Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNormalizerAttributes::explodeRange(\Symfony\Component\JsonEncoder\Decode\NativeDecoder::decodeStream($stream, $v[0], $v[1]), $options),
                    default => null,
                };
            }
        });
    };
    return $providers['Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNormalizerAttributes']($stream, 0, null);
};
