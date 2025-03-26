<?php

return static function (mixed $stream, \Psr\Container\ContainerInterface $valueTransformers, \Symfony\Component\JsonStreamer\Read\LazyInstantiator $instantiator, array $options): mixed {
    $providers['array<string,Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy>'] = static function ($stream, $offset, $length) use ($options, $valueTransformers, $instantiator, &$providers) {
        $data = \Symfony\Component\JsonStreamer\Read\Splitter::splitDict($stream, $offset, $length);
        $iterable = static function ($stream, $data) use ($options, $valueTransformers, $instantiator, &$providers) {
            foreach ($data as $k => $v) {
                yield $k => $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy']($stream, $v[0], $v[1]);
            }
        };
        return \iterator_to_array($iterable($stream, $data));
    };
    $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy'] = static function ($stream, $offset, $length) use ($options, $valueTransformers, $instantiator, &$providers) {
        $data = \Symfony\Component\JsonStreamer\Read\Splitter::splitDict($stream, $offset, $length);
        return $instantiator->instantiate(\Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy::class, static function ($object) use ($stream, $data, $options, $valueTransformers, $instantiator, &$providers) {
            foreach ($data as $k => $v) {
                match ($k) {
                    'id' => $object->id = \Symfony\Component\JsonStreamer\Read\Decoder::decodeStream($stream, $v[0], $v[1]),
                    'name' => $object->name = \Symfony\Component\JsonStreamer\Read\Decoder::decodeStream($stream, $v[0], $v[1]),
                    default => null,
                };
            }
        });
    };
    return $providers['array<string,Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy>']($stream, 0, null);
};
