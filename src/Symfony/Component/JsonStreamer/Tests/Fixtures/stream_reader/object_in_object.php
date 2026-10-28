<?php

declare(strict_types=1);

/**
 * @return Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithOtherDummies
 */
return static function (string|\Stringable $string, \Psr\Container\ContainerInterface $transformers, array $options): mixed {
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithOtherDummies'] = static function ($data) use ($options, $transformers, &$providers) {
        if (!\is_array($data)) {
            throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithOtherDummies'));
        }
        $object = new \Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithOtherDummies();
        if (\array_key_exists('name', $data)) {
            $value = ($data['name'] ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithOtherDummies::$name".'));
            try {
                $object->{'name'} = $value;
            } catch (\TypeError $e) {
                throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException($e->getMessage(), 0, $e);
            }
        }
        if (\array_key_exists('otherDummyOne', $data)) {
            $value = $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithNameAttributes'](($data['otherDummyOne'] ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithOtherDummies::$otherDummyOne".')));
            try {
                $object->{'otherDummyOne'} = $value;
            } catch (\TypeError $e) {
                throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException($e->getMessage(), 0, $e);
            }
        }
        if (\array_key_exists('otherDummyTwo', $data)) {
            $value = $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy'](($data['otherDummyTwo'] ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithOtherDummies::$otherDummyTwo".')));
            try {
                $object->{'otherDummyTwo'} = $value;
            } catch (\TypeError $e) {
                throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException($e->getMessage(), 0, $e);
            }
        }
        return $object;
    };
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithNameAttributes'] = static function ($data) use ($options, $transformers, &$providers) {
        if (!\is_array($data)) {
            throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithNameAttributes'));
        }
        $object = new \Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithNameAttributes();
        if (\array_key_exists('@id', $data)) {
            $value = ($data['@id'] ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithNameAttributes::$id".'));
            try {
                $object->{'id'} = $value;
            } catch (\TypeError $e) {
                throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException($e->getMessage(), 0, $e);
            }
        }
        if (\array_key_exists('name', $data)) {
            $value = ($data['name'] ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithNameAttributes::$name".'));
            try {
                $object->{'name'} = $value;
            } catch (\TypeError $e) {
                throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException($e->getMessage(), 0, $e);
            }
        }
        return $object;
    };
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy'] = static function ($data) use ($options, $transformers, &$providers) {
        if (!\is_array($data)) {
            throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy'));
        }
        $object = new \Symfony\Component\JsonStreamer\Tests\Fixtures\Model\ClassicDummy();
        if (\array_key_exists('id', $data)) {
            $value = ($data['id'] ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy::$id".'));
            try {
                $object->{'id'} = $value;
            } catch (\TypeError $e) {
                throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException($e->getMessage(), 0, $e);
            }
        }
        if (\array_key_exists('name', $data)) {
            $value = ($data['name'] ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\ClassicDummy::$name".'));
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
