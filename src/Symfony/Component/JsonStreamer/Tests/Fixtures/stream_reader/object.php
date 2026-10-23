<?php

/**
 * @return Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy
 */
return static function (string|\Stringable $string, \Psr\Container\ContainerInterface $transformers, array $options): mixed {
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
    return $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy'](\Symfony\Component\JsonStreamer\Read\Decoder::decodeString((string) $string));
};
