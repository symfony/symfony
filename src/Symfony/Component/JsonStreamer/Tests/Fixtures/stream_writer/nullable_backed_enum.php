<?php

/**
 * @param Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum|null $data
 */
return static function (mixed $data, \Psr\Container\ContainerInterface $transformers, array $options): \Traversable {
    try {
        $buffer = '';
        if ($data instanceof \Symfony\Component\JsonStreamer\Tests\Fixtures\Enum\DummyBackedEnum) {
            $buffer .= \json_encode($data->value, \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE, 512);
        } elseif (null === $data) {
            $buffer .= "null";
        } else {
            throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value.', \get_debug_type($data)));
        }
        if ('' !== $buffer) {
            yield $buffer;
        }
    } catch (\JsonException $e) {
        throw new \Symfony\Component\JsonStreamer\Exception\NotEncodableValueException('Cannot encode "Symfony\\Component\\JsonStreamer\\Tests\\Fixtures\\Enum\\DummyBackedEnum|null" to JSON: '.$e->getMessage(), 0, $e);
    }
};
