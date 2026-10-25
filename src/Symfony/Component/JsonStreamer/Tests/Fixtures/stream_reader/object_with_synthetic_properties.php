<?php

/**
 * @return Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithSyntheticProperties
 */
return static function (string|\Stringable $string, \Psr\Container\ContainerInterface $transformers, \Symfony\Component\JsonStreamer\Read\Instantiator $instantiator, array $options): mixed {
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithSyntheticProperties'] = static function ($data) use ($options, $transformers, $instantiator, &$providers) {
        return $instantiator->instantiate(\Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithSyntheticProperties::class, []);
    };
    return $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithSyntheticProperties'](\Symfony\Component\JsonStreamer\Read\Decoder::decodeString((string) $string));
};
