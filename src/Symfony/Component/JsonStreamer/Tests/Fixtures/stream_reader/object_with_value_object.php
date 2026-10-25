<?php

/**
 * @return Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithDateTimes
 */
return static function (string|\Stringable $string, \Psr\Container\ContainerInterface $transformers, \Symfony\Component\JsonStreamer\Read\Instantiator $instantiator, array $options): mixed {
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithDateTimes'] = static function ($data) use ($options, $transformers, $instantiator, &$providers) {
        return $instantiator->instantiate(\Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithDateTimes::class, [...(\array_key_exists('interface', $data) ? ['interface' => $providers['DateTimeInterface']($data['interface'])] : []), ...(\array_key_exists('immutable', $data) ? ['immutable' => $providers['DateTimeImmutable']($data['immutable'])] : []), ...(\array_key_exists('union', $data) ? ['union' => $providers['DateTimeImmutable|int']($data['union'])] : [])]);
    };
    $providers['DateTimeInterface'] = static function ($data) use ($options, $transformers, $instantiator, &$providers) {
        return $transformers->get('DateTimeInterface')->reverseTransform($data, $options);
    };
    $providers['DateTimeImmutable'] = static function ($data) use ($options, $transformers, $instantiator, &$providers) {
        return $transformers->get('DateTimeInterface')->reverseTransform($data, $options);
    };
    $providers['DateTimeImmutable|int'] = static function ($data) use ($options, $transformers, $instantiator, &$providers) {
        if (\is_string($data)) {
            return $providers['DateTimeImmutable']($data);
        }
        if (\is_int($data)) {
            return $data;
        }
        throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'DateTimeImmutable|int'));
    };
    return $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithDateTimes'](\Symfony\Component\JsonStreamer\Read\Decoder::decodeString((string) $string));
};
