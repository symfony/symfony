<?php

declare(strict_types=1);

/**
 * @return Symfony\Component\JsonStreamer\Tests\Fixtures\Model\SelfReferencingDummyList
 */
return static function (string|\Stringable $string, \Psr\Container\ContainerInterface $transformers, array $options): mixed {
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\SelfReferencingDummyList'] = static function ($data) use ($options, $transformers, &$providers) {
        if (!\is_array($data)) {
            throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\SelfReferencingDummyList'));
        }
        $object = new \Symfony\Component\JsonStreamer\Tests\Fixtures\Model\SelfReferencingDummyList();
        if (\array_key_exists('items', $data)) {
            $value = $providers['array<int|string, Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\SelfReferencingDummyList>'](($data['items'] ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\SelfReferencingDummyList::$items".')));
            try {
                $object->{'items'} = $value;
            } catch (\TypeError $e) {
                throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException($e->getMessage(), 0, $e);
            }
        }
        return $object;
    };
    $providers['array<int|string, Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\SelfReferencingDummyList>'] = static function ($data) use ($options, $transformers, &$providers) {
        if (!\is_array($data)) {
            throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'array<int|string, Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\SelfReferencingDummyList>'));
        }
        $collection = [];
        foreach ($data as $k => $v) {
            $collection[$k] = $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\SelfReferencingDummyList']($v);
        }
        return $collection;
    };
    return $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\SelfReferencingDummyList'](\Symfony\Component\JsonStreamer\Read\Decoder::decodeString((string) $string));
};
