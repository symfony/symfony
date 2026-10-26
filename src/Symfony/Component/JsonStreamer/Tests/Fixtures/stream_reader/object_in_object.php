<?php

/**
 * @return Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithOtherDummies
 */
return static function (string|\Stringable $string, \Psr\Container\ContainerInterface $transformers, \Symfony\Component\JsonStreamer\Read\Instantiator $instantiator, array $options): mixed {
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithOtherDummies'] = static function ($data) use ($options, $transformers, $instantiator, &$providers) {
        if (!\is_array($data)) {
            throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithOtherDummies'));
        }
        return $instantiator->instantiate(\Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithOtherDummies::class, [...(\array_key_exists('name', $data) ? ['name' => ($data['name'] ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithOtherDummies::$name".'))] : []), ...(\array_key_exists('otherDummyOne', $data) ? ['otherDummyOne' => $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithNameAttributes'](($data['otherDummyOne'] ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithOtherDummies::$otherDummyOne".')))] : []), ...(\array_key_exists('otherDummyTwo', $data) ? ['otherDummyTwo' => $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy'](($data['otherDummyTwo'] ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithOtherDummies::$otherDummyTwo".')))] : [])]);
    };
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithNameAttributes'] = static function ($data) use ($options, $transformers, $instantiator, &$providers) {
        if (!\is_array($data)) {
            throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithNameAttributes'));
        }
        return $instantiator->instantiate(\Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNameAttributes::class, [...(\array_key_exists('@id', $data) ? ['id' => ($data['@id'] ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithNameAttributes::$id".'))] : []), ...(\array_key_exists('name', $data) ? ['name' => ($data['name'] ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithNameAttributes::$name".'))] : [])]);
    };
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy'] = static function ($data) use ($options, $transformers, $instantiator, &$providers) {
        if (!\is_array($data)) {
            throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy'));
        }
        return $instantiator->instantiate(\Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy::class, [...(\array_key_exists('id', $data) ? ['id' => ($data['id'] ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy::$id".'))] : []), ...(\array_key_exists('name', $data) ? ['name' => ($data['name'] ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy::$name".'))] : [])]);
    };
    return $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithOtherDummies'](\Symfony\Component\JsonStreamer\Read\Decoder::decodeString((string) $string));
};
