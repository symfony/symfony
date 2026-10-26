<?php

/**
 * @return Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNullableRepeatedDummies
 */
return static function (string|\Stringable $string, \Psr\Container\ContainerInterface $valueTransformers, \Symfony\Component\JsonStreamer\Read\Instantiator $instantiator, array $options): mixed {
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithNullableRepeatedDummies'] = static function ($data) use ($options, $valueTransformers, $instantiator, &$providers) {
        if (!\is_array($data)) {
            throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithNullableRepeatedDummies'));
        }
        return $instantiator->instantiate(\Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNullableRepeatedDummies::class, [...(\array_key_exists('first', $data) ? ['first' => $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy|null']($data['first'])] : []), ...(\array_key_exists('second', $data) ? ['second' => $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy|null']($data['second'])] : [])]);
    };
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy'] = static function ($data) use ($options, $valueTransformers, $instantiator, &$providers) {
        if (!\is_array($data)) {
            throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy'));
        }
        return $instantiator->instantiate(\Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy::class, [...(\array_key_exists('id', $data) ? ['id' => ($data['id'] ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy::$id".'))] : []), ...(\array_key_exists('name', $data) ? ['name' => ($data['name'] ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy::$name".'))] : [])]);
    };
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy|null'] = static function ($data) use ($options, $valueTransformers, $instantiator, &$providers) {
        if (\is_array($data)) {
            return $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy']($data);
        }
        if (null === $data) {
            return null;
        }
        throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy|null'));
    };
    return $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithNullableRepeatedDummies'](\Symfony\Component\JsonStreamer\Read\Decoder::decodeString((string) $string));
};
