<?php

return static function (mixed $stream, \Psr\Container\ContainerInterface $denormalizers, \Symfony\Component\JsonEncoder\Decode\LazyInstantiator $instantiator, array $options): mixed {
    $providers['Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithOtherDummies'] = static function ($stream, $offset, $length) use ($options, $denormalizers, $instantiator, &$providers) {
        $data = \Symfony\Component\JsonEncoder\Decode\Splitter::splitDict($stream, $offset, $length);
        return $instantiator->instantiate(\Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithOtherDummies::class, static function ($object) use ($stream, $data, $options, $denormalizers, $instantiator, &$providers) {
            foreach ($data as $k => $v) {
                match ($k) {
                    'name' => $object->name = \Symfony\Component\JsonEncoder\Decode\NativeDecoder::decodeStream($stream, $v[0], $v[1]),
                    'otherDummyOne' => $object->otherDummyOne = $providers['Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNameAttributes']($stream, $v[0], $v[1]),
                    'otherDummyTwo' => $object->otherDummyTwo = $providers['Symfony\Component\JsonEncoder\Tests\Fixtures\Model\ClassicDummy']($stream, $v[0], $v[1]),
                    default => null,
                };
            }
        });
    };
    $providers['Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNameAttributes'] = static function ($stream, $offset, $length) use ($options, $denormalizers, $instantiator, &$providers) {
        $data = \Symfony\Component\JsonEncoder\Decode\Splitter::splitDict($stream, $offset, $length);
        return $instantiator->instantiate(\Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNameAttributes::class, static function ($object) use ($stream, $data, $options, $denormalizers, $instantiator, &$providers) {
            foreach ($data as $k => $v) {
                match ($k) {
                    '@id' => $object->id = \Symfony\Component\JsonEncoder\Decode\NativeDecoder::decodeStream($stream, $v[0], $v[1]),
                    'name' => $object->name = \Symfony\Component\JsonEncoder\Decode\NativeDecoder::decodeStream($stream, $v[0], $v[1]),
                    default => null,
                };
            }
        });
    };
    $providers['Symfony\Component\JsonEncoder\Tests\Fixtures\Model\ClassicDummy'] = static function ($stream, $offset, $length) use ($options, $denormalizers, $instantiator, &$providers) {
        $data = \Symfony\Component\JsonEncoder\Decode\Splitter::splitDict($stream, $offset, $length);
        return $instantiator->instantiate(\Symfony\Component\JsonEncoder\Tests\Fixtures\Model\ClassicDummy::class, static function ($object) use ($stream, $data, $options, $denormalizers, $instantiator, &$providers) {
            foreach ($data as $k => $v) {
                match ($k) {
                    'id' => $object->id = \Symfony\Component\JsonEncoder\Decode\NativeDecoder::decodeStream($stream, $v[0], $v[1]),
                    'name' => $object->name = \Symfony\Component\JsonEncoder\Decode\NativeDecoder::decodeStream($stream, $v[0], $v[1]),
                    default => null,
                };
            }
        });
    };
    return $providers['Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithOtherDummies']($stream, 0, null);
};
