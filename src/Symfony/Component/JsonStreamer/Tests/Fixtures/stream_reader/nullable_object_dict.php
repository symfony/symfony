<?php

/**
 * @return array<string, Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy>|null
 */
return static function (string|\Stringable $string, \Psr\Container\ContainerInterface $transformers, array $options): mixed {
    $providers['array<string, Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy>'] = static function ($data) use ($options, $transformers, &$providers) {
        $collection = [];
        foreach ($data as $k => $v) {
            $collection[$k] = $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy']($v);
        }
        return $collection;
    };
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy'] = static function ($data) use ($options, $transformers, &$providers) {
        $object = new \Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy();
        if (isset($data['id'])) {
            $value = $data['id'];
            try {
                $object->{'id'} = $value;
            } catch (\TypeError $e) {
                throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException($e->getMessage(), 0, $e);
            }
        }
        if (isset($data['name'])) {
            $value = $data['name'];
            try {
                $object->{'name'} = $value;
            } catch (\TypeError $e) {
                throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException($e->getMessage(), 0, $e);
            }
        }
        return $object;
    };
    $providers['array<string, Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy>|null'] = static function ($data) use ($options, $transformers, &$providers) {
        if (\is_array($data)) {
            return $providers['array<string, Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy>']($data);
        }
        if (null === $data) {
            return null;
        }
        throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'array<string, Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy>|null'));
    };
    return $providers['array<string, Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy>|null'](\Symfony\Component\JsonStreamer\Read\Decoder::decodeString((string) $string));
};
