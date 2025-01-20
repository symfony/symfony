<?php

return static function (mixed $data, \Psr\Container\ContainerInterface $normalizers, array $options): \Traversable {
    yield '{"value":';
    if ($data->value instanceof \Symfony\Component\JsonEncoder\Tests\Fixtures\Enum\DummyBackedEnum) {
        yield \json_encode($data->value->value);
    } elseif (null === $data->value) {
        yield 'null';
    } elseif (\is_string($data->value)) {
        yield \json_encode($data->value);
    } else {
        throw new \Symfony\Component\JsonEncoder\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value.', \get_debug_type($data->value)));
    }
    yield '}';
};
