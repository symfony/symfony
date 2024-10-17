<?php

return static function (mixed $data, \Psr\Container\ContainerInterface $normalizers, array $options): \Traversable {
    if ($data instanceof \Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNameAttributes) {
        yield '{"@id":';
        yield \json_encode($data->id);
        yield ',"name":';
        yield \json_encode($data->name);
        yield '}';
    } elseif (null === $data) {
        yield 'null';
    } else {
        throw new \Symfony\Component\JsonEncoder\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value.', \get_debug_type($data)));
    }
};
