<?php

/**
 * @return Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithDateTimes
 */
return static function (mixed $stream, \Psr\Container\ContainerInterface $transformers, \Symfony\Component\JsonStreamer\Read\LazyInstantiator $instantiator, array $options): mixed {
    $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithDateTimes'] = static function ($stream, $offset, $length) use ($options, $transformers, $instantiator, &$providers) {
        $data = \Symfony\Component\JsonStreamer\Read\Splitter::splitDict($stream, $offset, $length);
        return $instantiator->instantiate(\Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithDateTimes::class, static function ($object) use ($stream, $data, $options, $transformers, $instantiator, &$providers) {
            foreach ($data as $k => $v) {
                match ($k) {
                    'interface' => $object->interface = $providers['DateTimeInterface']($stream, $v[0], $v[1]),
                    'immutable' => $object->immutable = $providers['DateTimeImmutable']($stream, $v[0], $v[1]),
                    'union' => $object->union = $providers['DateTimeImmutable|int']($stream, $v[0], $v[1]),
                    default => null,
                };
            }
        });
    };
    $providers['DateTimeInterface'] = static function ($stream, $offset, $length) use ($options, $transformers, $instantiator, &$providers) {
        return $transformers->get('DateTimeInterface')->reverseTransform(\Symfony\Component\JsonStreamer\Read\Decoder::decodeStream($stream, $offset, $length), $options);
    };
    $providers['DateTimeImmutable'] = static function ($stream, $offset, $length) use ($options, $transformers, $instantiator, &$providers) {
        return $transformers->get('DateTimeInterface')->reverseTransform(\Symfony\Component\JsonStreamer\Read\Decoder::decodeStream($stream, $offset, $length), $options);
    };
    $providers['DateTimeImmutable|int'] = static function ($stream, $offset, $length) use ($options, $transformers, $instantiator, &$providers) {
        $data = \Symfony\Component\JsonStreamer\Read\Decoder::decodeStream($stream, $offset, $length);
        if (\is_string($data)) {
            return $providers['DateTimeImmutable']($stream, $offset, $length);
        }
        if (\is_int($data)) {
            return $data;
        }
        throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'DateTimeImmutable|int'));
    };
    return $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithDateTimes']($stream, 0, null);
};
