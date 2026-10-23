<?php

/**
 * @param Symfony\Component\JsonStreamer\Tests\Fixtures\Model\SelfReferencingDummyDict $data
 */
return static function (mixed $data, \Psr\Container\ContainerInterface $transformers, array $options): \Traversable {
    $generators['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\SelfReferencingDummyDict'] = static function ($data, $depth, &$buffer) use ($transformers, $options, &$generators) {
        if ($depth >= 512) {
            throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException('Maximum stack depth exceeded');
        }
        $buffer .= "{\"items\":{";
        $prefix2 = '';
        foreach ($data->items as $key1 => $value1) {
            $key1 = \substr(\json_encode($key1, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE), 1, -1);
            $buffer .= "{$prefix2}\"{$key1}\":";
            yield from $generators['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\SelfReferencingDummyDict']($value1, $depth + 1, $buffer);
            $prefix2 = ',';
            if (\strlen($buffer) >= 8192) {
                yield $buffer;
                $buffer = '';
            }
        }
        $buffer .= "}}";
        if (\strlen($buffer) >= 8192) {
            yield $buffer;
            $buffer = '';
        }
    };
    try {
        $buffer = '';
        yield from $generators['Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\SelfReferencingDummyDict']($data, 0, $buffer);
        if ('' !== $buffer) {
            yield $buffer;
        }
    } catch (\JsonException $e) {
        throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException('Cannot encode "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Model\\SelfReferencingDummyDict" to JSON: '.$e->getMessage(), 0, $e);
    }
};
