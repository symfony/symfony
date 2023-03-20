<?php

return static function (string|\Stringable $string, array $config, \Symfony\Component\JsonEncoder\Decode\Instantiator $instantiator, ?\Psr\Container\ContainerInterface $services): mixed {
    $providers['array<string,Symfony\Component\JsonEncoder\Tests\Fixtures\Model\ClassicDummy>'] = static function ($data) use ($config, $instantiator, $services, &$providers) {
        $iterable = static function ($data) use ($config, $instantiator, $services, &$providers) {
            foreach ($data as $k => $v) {
                yield $k => $providers['Symfony\Component\JsonEncoder\Tests\Fixtures\Model\ClassicDummy']($v);
            }
        };
        return \iterator_to_array($iterable($data));
    };
    $providers['Symfony\Component\JsonEncoder\Tests\Fixtures\Model\ClassicDummy'] = static function ($data) use ($config, $instantiator, $services, &$providers) {
        return $instantiator->instantiate(\Symfony\Component\JsonEncoder\Tests\Fixtures\Model\ClassicDummy::class, \array_filter(['id' => $data['id'] ?? '_symfony_missing_value', 'name' => $data['name'] ?? '_symfony_missing_value'], static function ($v) {
            return '_symfony_missing_value' !== $v;
        }));
    };
    $providers['array<string,Symfony\Component\JsonEncoder\Tests\Fixtures\Model\ClassicDummy>|null'] = static function ($data) use ($config, $instantiator, $services, &$providers) {
        if (\is_array($data)) {
            return $providers['array<string,Symfony\Component\JsonEncoder\Tests\Fixtures\Model\ClassicDummy>']($data);
        }
        if (null === $data) {
            return null;
        }
        throw new \Symfony\Component\JsonEncoder\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "array<string,Symfony\Component\JsonEncoder\Tests\Fixtures\Model\ClassicDummy>|null".', \get_debug_type($data)));
    };
    return $providers['array<string,Symfony\Component\JsonEncoder\Tests\Fixtures\Model\ClassicDummy>|null'](\Symfony\Component\JsonEncoder\Decode\NativeDecoder::decodeString((string) $string));
};
