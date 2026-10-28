<?php

declare(strict_types=1);

/**
 * @return array{'id': int, 'name': string}
 */
return static function (mixed $stream, \Psr\Container\ContainerInterface $transformers, \Symfony\Component\JsonStreamer\Read\LazyInstantiator $instantiator, array $options): mixed {
    $providers['array{\'id\': int, \'name\': string}'] = static function ($stream, $offset, $length) use ($options, $transformers, $instantiator, &$providers) {
        $data = \Symfony\Component\JsonStreamer\Read\Splitter::splitDict($stream, $offset, $length);
        if (null === $data) {
            throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'array{\'id\': int, \'name\': string}'));
        }
        $collection = [];
        foreach ($data as $k => $v) {
            $collection[$k] = \Symfony\Component\JsonStreamer\Read\Decoder::decodeStream($stream, $v[0], $v[1]);
        }
        return $collection;
    };
    return $providers['array{\'id\': int, \'name\': string}']($stream, 0, null);
};
