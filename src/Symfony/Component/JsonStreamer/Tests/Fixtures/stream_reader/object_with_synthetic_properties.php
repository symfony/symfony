<?php

/**
 * @return Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithSyntheticProperties
 */
return static function (string|\Stringable $string, \Psr\Container\ContainerInterface $transformers, array $options): mixed {
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithSyntheticProperties'] = static function ($data) use ($options, $transformers, &$providers) {
        $object = new \Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithSyntheticProperties();
        return $object;
    };
    return $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithSyntheticProperties'](\Symfony\Component\JsonStreamer\Read\Decoder::decodeString((string) $string));
};
