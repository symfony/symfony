<?php

return static function (mixed $stream, \Psr\Container\ContainerInterface $denormalizers, \Symfony\Component\JsonEncoder\Decode\LazyInstantiator $instantiator, array $options): mixed {
    $providers['iterable<int,mixed>'] = static function ($stream, $offset, $length) use ($options, $denormalizers, $instantiator, &$providers) {
        $data = \Symfony\Component\JsonEncoder\Decode\Splitter::splitList($stream, $offset, $length);
        $iterable = static function ($stream, $data) use ($options, $denormalizers, $instantiator, &$providers) {
            foreach ($data as $k => $v) {
                yield $k => \Symfony\Component\JsonEncoder\Decode\NativeDecoder::decodeStream($stream, $v[0], $v[1]);
            }
        };
        return $iterable($stream, $data);
    };
    return $providers['iterable<int,mixed>']($stream, 0, null);
};
