<?php

/**
 * @return iterable<int|string, Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy>
 */
return static function (string|\Stringable $string, \Psr\Container\ContainerInterface $transformers, \Symfony\Component\JsonStreamer\Read\Instantiator $instantiator, array $options): mixed {
    $providers['iterable<int|string, Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy>'] = static function ($data) use ($options, $transformers, $instantiator, &$providers) {
        $iterable = static function ($data) use ($options, $transformers, $instantiator, &$providers) {
            foreach ($data as $k => $v) {
                yield $k => $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy']($v);
            }
        };
        return $iterable($data);
    };
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy'] = static function ($data) use ($options, $transformers, $instantiator, &$providers) {
        return $instantiator->instantiate(\Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy::class, [...(isset($data['id']) ? ['id' => $data['id']] : []), ...(isset($data['name']) ? ['name' => $data['name']] : [])]);
    };
    return $providers['iterable<int|string, Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy>'](\Symfony\Component\JsonStreamer\Read\Decoder::decodeString((string) $string));
};
