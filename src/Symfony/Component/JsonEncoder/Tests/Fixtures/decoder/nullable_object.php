<?php

return static function (string|\Stringable $string, \Psr\Container\ContainerInterface $denormalizers, \Symfony\Component\JsonEncoder\Decode\Instantiator $instantiator, array $options): mixed {
    $providers['Symfony\Component\JsonEncoder\Tests\Fixtures\Model\ClassicDummy'] = static function ($data) use ($options, $denormalizers, $instantiator, &$providers) {
        return $instantiator->instantiate(\Symfony\Component\JsonEncoder\Tests\Fixtures\Model\ClassicDummy::class, \array_filter(['id' => $data['id'] ?? '_symfony_missing_value', 'name' => $data['name'] ?? '_symfony_missing_value'], static function ($v) {
            return '_symfony_missing_value' !== $v;
        }));
    };
    $providers['Symfony\Component\JsonEncoder\Tests\Fixtures\Model\ClassicDummy|null'] = static function ($data) use ($options, $denormalizers, $instantiator, &$providers) {
        if (\is_array($data)) {
            return $providers['Symfony\Component\JsonEncoder\Tests\Fixtures\Model\ClassicDummy']($data);
        }
        if (null === $data) {
            return null;
        }
        throw new \Symfony\Component\JsonEncoder\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "Symfony\Component\JsonEncoder\Tests\Fixtures\Model\ClassicDummy|null".', \get_debug_type($data)));
    };
    return $providers['Symfony\Component\JsonEncoder\Tests\Fixtures\Model\ClassicDummy|null'](\Symfony\Component\JsonEncoder\Decode\NativeDecoder::decodeString((string) $string));
};
