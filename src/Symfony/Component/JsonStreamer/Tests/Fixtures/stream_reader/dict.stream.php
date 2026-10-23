<?php

/**
 * @return array<string, mixed>
 */
return static function (mixed $stream, \Psr\Container\ContainerInterface $transformers, \Symfony\Component\JsonStreamer\Read\LazyInstantiator $instantiator, array $options): mixed {
    $providers['array<string, mixed>'] = static function ($stream, $offset, $length) use ($options, $transformers, $instantiator, &$providers) {
        $data = \Symfony\Component\JsonStreamer\Read\Splitter::splitDict($stream, $offset, $length);
        $collection = [];
        foreach ($data as $k => $v) {
            $collection[$k] = \Symfony\Component\JsonStreamer\Read\Decoder::decodeStream($stream, $v[0], $v[1]);
        }
        return $collection;
    };
    return $providers['array<string, mixed>']($stream, 0, null);
};
