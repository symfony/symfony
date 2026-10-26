<?php

/**
 * @return Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithSyntheticProperties
 */
return static function (string|\Stringable $string, \Psr\Container\ContainerInterface $valueTransformers, \Symfony\Component\JsonStreamer\Read\Instantiator $instantiator, array $options): mixed {
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithSyntheticProperties'] = static function ($data) use ($options, $valueTransformers, $instantiator, &$providers) {
        if (!\is_array($data)) {
            throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithSyntheticProperties'));
        }
        return $instantiator->instantiate(\Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithSyntheticProperties::class, []);
    };
    return $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithSyntheticProperties'](\Symfony\Component\JsonStreamer\Read\Decoder::decodeString((string) $string));
};
