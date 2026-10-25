<?php

/**
 * @return Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithOtherDummies
 */
return static function (string|\Stringable $string, \Psr\Container\ContainerInterface $valueTransformers, \Symfony\Component\JsonStreamer\Read\Instantiator $instantiator, array $options): mixed {
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithOtherDummies'] = static function ($data) use ($options, $valueTransformers, $instantiator, &$providers) {
        return $instantiator->instantiate(\Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithOtherDummies::class, [...(isset($data['name']) ? ['name' => $data['name']] : []), ...(\array_key_exists('otherDummyOne', $data) ? ['otherDummyOne' => $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithNameAttributes']($data['otherDummyOne'])] : []), ...(\array_key_exists('otherDummyTwo', $data) ? ['otherDummyTwo' => $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy']($data['otherDummyTwo'])] : [])]);
    };
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithNameAttributes'] = static function ($data) use ($options, $valueTransformers, $instantiator, &$providers) {
        return $instantiator->instantiate(\Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNameAttributes::class, [...(isset($data['@id']) ? ['id' => $data['@id']] : []), ...(isset($data['name']) ? ['name' => $data['name']] : [])]);
    };
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy'] = static function ($data) use ($options, $valueTransformers, $instantiator, &$providers) {
        return $instantiator->instantiate(\Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy::class, [...(isset($data['id']) ? ['id' => $data['id']] : []), ...(isset($data['name']) ? ['name' => $data['name']] : [])]);
    };
    return $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithOtherDummies'](\Symfony\Component\JsonStreamer\Read\Decoder::decodeString((string) $string));
};
