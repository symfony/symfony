<?php

/**
 * @return Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithOtherDummies
 */
return static function (string|\Stringable $string, \Psr\Container\ContainerInterface $transformers, array $options): mixed {
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithOtherDummies'] = static function ($data) use ($options, $transformers, &$providers) {
        $object = new \Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithOtherDummies();
        if (isset($data['name'])) {
            $value = $data['name'];
            try {
                $object->{'name'} = $value;
            } catch (\TypeError $e) {
                throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException($e->getMessage(), 0, $e);
            }
        }
        if (\array_key_exists('otherDummyOne', $data)) {
            $value = $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithNameAttributes']($data['otherDummyOne']);
            try {
                $object->{'otherDummyOne'} = $value;
            } catch (\TypeError $e) {
                throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException($e->getMessage(), 0, $e);
            }
        }
        if (\array_key_exists('otherDummyTwo', $data)) {
            $value = $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy']($data['otherDummyTwo']);
            try {
                $object->{'otherDummyTwo'} = $value;
            } catch (\TypeError $e) {
                throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException($e->getMessage(), 0, $e);
            }
        }
        return $object;
    };
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithNameAttributes'] = static function ($data) use ($options, $transformers, &$providers) {
        $object = new \Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNameAttributes();
        if (isset($data['@id'])) {
            $value = $data['@id'];
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
    return $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithOtherDummies'](\Symfony\Component\JsonStreamer\Read\Decoder::decodeString((string) $string));
};
