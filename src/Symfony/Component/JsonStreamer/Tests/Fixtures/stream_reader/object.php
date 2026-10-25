<?php

/**
 * @return Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy
 */
return static function (string|\Stringable $string, \Psr\Container\ContainerInterface $valueTransformers, \Symfony\Component\JsonStreamer\Read\Instantiator $instantiator, array $options): mixed {
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy'] = static function ($data) use ($options, $valueTransformers, $instantiator, &$providers) {
        return $instantiator->instantiate(\Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy::class, [...(isset($data['id']) ? ['id' => $data['id']] : []), ...(isset($data['name']) ? ['name' => $data['name']] : [])]);
    };
    return $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy'](\Symfony\Component\JsonStreamer\Read\Decoder::decodeString((string) $string));
};
