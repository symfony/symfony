<?php

return static function (mixed $stream, \Psr\Container\ContainerInterface $valueTransformers, \Symfony\Component\JsonStreamer\Read\LazyInstantiator $instantiator, array $options): mixed {
    $providers['iterable'] = static function ($stream, $offset, $length) use ($options, $valueTransformers, $instantiator, &$providers) {
        $data = \Symfony\Component\JsonStreamer\Read\Splitter::splitDict($stream, $offset, $length);
        $iterable = static function ($stream, $data) use ($options, $valueTransformers, $instantiator, &$providers) {
            foreach ($data as $k => $v) {
                yield $k => \Symfony\Component\JsonStreamer\Read\Decoder::decodeStream($stream, $v[0], $v[1]);
            }
        };
        return $iterable($stream, $data);
    };
    return $providers['iterable']($stream, 0, null);
};
