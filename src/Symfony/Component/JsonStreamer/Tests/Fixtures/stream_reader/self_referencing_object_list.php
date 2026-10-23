<?php

/**
 * @return Symfony\Component\JsonStreamer\Tests\Fixtures\Model\SelfReferencingDummyList
 */
return static function (string|\Stringable $string, \Psr\Container\ContainerInterface $transformers, array $options): mixed {
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\SelfReferencingDummyList'] = static function ($data) use ($options, $transformers, &$providers) {
        $object = new \Symfony\Component\JsonStreamer\Tests\Fixtures\Model\SelfReferencingDummyList();
        if (\array_key_exists('items', $data)) {
            $value = $providers['array<int|string, Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\SelfReferencingDummyList>']($data['items']);
            try {
                $object->{'items'} = $value;
            } catch (\TypeError $e) {
                throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException($e->getMessage(), 0, $e);
            }
        }
        return $object;
    };
    $providers['array<int|string, Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\SelfReferencingDummyList>'] = static function ($data) use ($options, $transformers, &$providers) {
        $collection = [];
        foreach ($data as $k => $v) {
            $collection[$k] = $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\SelfReferencingDummyList']($v);
        }
        return $collection;
    };
    return $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\SelfReferencingDummyList'](\Symfony\Component\JsonStreamer\Read\Decoder::decodeString((string) $string));
};
