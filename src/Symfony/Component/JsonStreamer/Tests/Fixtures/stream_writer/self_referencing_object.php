<?php

/**
 * @param Symfony\Component\JsonStreamer\Tests\Fixtures\Model\SelfReferencingDummy $data
 */
return static function (mixed $data, \Psr\Container\ContainerInterface $transformers, array $options): \Traversable {
    $generators['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\SelfReferencingDummy'] = static function ($data, $depth, &$buffer) use ($transformers, $options, &$generators) {
        if ($depth >= 512) {
            throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException('Maximum stack depth exceeded');
        }
        $buffer .= "{";
        $prefix1 = '';
        if (null === $data->self && ($options['include_null_properties'] ?? false)) {
            $buffer .= "{$prefix1}\"@self\":null";
        }
        if (null !== $data->self) {
            $buffer .= "{$prefix1}\"@self\":";
            yield from $generators['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\SelfReferencingDummy']($data->self, $depth + 1, $buffer);
        }
        $buffer .= "}";
        if (\strlen($buffer) >= 8192) {
            yield $buffer;
            $buffer = '';
        }
    };
    try {
        $buffer = '';
        yield from $generators['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\SelfReferencingDummy']($data, 0, $buffer);
        if ('' !== $buffer) {
            yield $buffer;
        }
    } catch (\JsonException $e) {
        throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException('Cannot encode "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\SelfReferencingDummy" to JSON: '.$e->getMessage(), 0, $e);
    }
};
