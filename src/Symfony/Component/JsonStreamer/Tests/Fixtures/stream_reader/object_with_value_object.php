<?php

/**
 * @return Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithDateTimes
 */
return static function (string|\Stringable $string, \Psr\Container\ContainerInterface $transformers, array $options): mixed {
    $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithDateTimes'] = static function ($data) use ($options, $transformers, &$providers) {
        if (!\is_array($data)) {
            throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithDateTimes'));
        }
        $object = new \Symfony\Component\JsonStreamer\Tests\Fixtures\Model\DummyWithDateTimes();
        if (\array_key_exists('interface', $data)) {
            $value = $providers['DateTimeInterface'](($data['interface'] ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithDateTimes::$interface".')));
            try {
                $object->{'interface'} = $value;
            } catch (\TypeError $e) {
                throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException($e->getMessage(), 0, $e);
            }
        }
        if (\array_key_exists('immutable', $data)) {
            $value = $providers['DateTimeImmutable'](($data['immutable'] ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithDateTimes::$immutable".')));
            try {
                $object->{'immutable'} = $value;
            } catch (\TypeError $e) {
                throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException($e->getMessage(), 0, $e);
            }
        }
        if (\array_key_exists('union', $data)) {
            $value = $providers['DateTimeImmutable|int'](($data['union'] ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithDateTimes::$union".')));
            try {
                $object->{'union'} = $value;
            } catch (\TypeError $e) {
                throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException($e->getMessage(), 0, $e);
            }
        }
        return $object;
    };
    $providers['DateTimeInterface'] = static function ($data) use ($options, $transformers, &$providers) {
        return $transformers->get('DateTimeInterface')->reverseTransform(($data ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "DateTimeInterface".')), $options);
    };
    $providers['DateTimeImmutable'] = static function ($data) use ($options, $transformers, &$providers) {
        return $transformers->get('DateTimeInterface')->reverseTransform(($data ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "DateTimeImmutable".')), $options);
    };
    $providers['DateTimeImmutable|int'] = static function ($data) use ($options, $transformers, &$providers) {
        if (\is_string($data)) {
            return $providers['DateTimeImmutable']($data);
        }
        if (\is_int($data)) {
            return $data;
        }
        throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value for "%s".', \get_debug_type($data), 'DateTimeImmutable|int'));
    };
    return $providers['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\DummyWithDateTimes'](\Symfony\Component\JsonStreamer\Read\Decoder::decodeString((string) $string));
};
