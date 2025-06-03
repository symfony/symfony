<?php

return static function (string|\Stringable $string, \Psr\Container\ContainerInterface $valueTransformers, \Symfony\Component\JsonStreamer\Read\Instantiator $instantiator, array $options): mixed {
    $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithOtherDummies'] = static function ($data) use ($options, $valueTransformers, $instantiator, &$providers) {
        return $instantiator->instantiate(\Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithOtherDummies::class, \array_filter(['name' => $data['name'] ?? '_symfony_missing_value', 'otherDummyOne' => \array_key_exists('otherDummyOne', $data) ? $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNameAttributes']($data['otherDummyOne']) : '_symfony_missing_value', 'otherDummyTwo' => \array_key_exists('otherDummyTwo', $data) ? $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy']($data['otherDummyTwo']) : '_symfony_missing_value'], static function ($v) {
            return '_symfony_missing_value' !== $v;
        }));
    };
    $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNameAttributes'] = static function ($data) use ($options, $valueTransformers, $instantiator, &$providers) {
        return $instantiator->instantiate(\Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNameAttributes::class, \array_filter(['id' => $data['@id'] ?? '_symfony_missing_value', 'name' => $data['name'] ?? '_symfony_missing_value'], static function ($v) {
            return '_symfony_missing_value' !== $v;
        }));
    };
    $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy'] = static function ($data) use ($options, $valueTransformers, $instantiator, &$providers) {
        return $instantiator->instantiate(\Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy::class, \array_filter(['id' => $data['id'] ?? '_symfony_missing_value', 'name' => $data['name'] ?? '_symfony_missing_value'], static function ($v) {
            return '_symfony_missing_value' !== $v;
        }));
    };
    return $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithOtherDummies'](\Symfony\Component\JsonStreamer\Read\Decoder::decodeString((string) $string));
};
