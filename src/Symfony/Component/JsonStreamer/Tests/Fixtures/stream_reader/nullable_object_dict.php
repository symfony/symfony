<?php

return static function (string|\Stringable $string, \Psr\Container\ContainerInterface $valueTransformers, \Symfony\Component\JsonStreamer\Read\Instantiator $instantiator, array $options): mixed {
    $providers['array<string,Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy>'] = static function ($data) use ($options, $valueTransformers, $instantiator, &$providers) {
        $iterable = static function ($data) use ($options, $valueTransformers, $instantiator, &$providers) {
            foreach ($data as $k => $v) {
                yield $k => $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy']($v);
            }
        };
        return \iterator_to_array($iterable($data));
    };
    $providers['Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy'] = static function ($data) use ($options, $valueTransformers, $instantiator, &$providers) {
        return $instantiator->instantiate(\Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy::class, \array_filter(['id' => $data['id'] ?? '_symfony_missing_value', 'name' => $data['name'] ?? '_symfony_missing_value'], static function ($v) {
            return '_symfony_missing_value' !== $v;
        }));
    };
    $providers['array<string,Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy>|null'] = static function ($data) use ($options, $valueTransformers, $instantiator, &$providers) {
        if (\is_array($data)) {
            return $providers['array<string,Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy>']($data);
        }
        if (null === $data) {
            return null;
        }
        throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "array<string,Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy>|null".', \get_debug_type($data)));
    };
    return $providers['array<string,Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy>|null'](\Symfony\Component\JsonStreamer\Read\Decoder::decodeString((string) $string));
};
