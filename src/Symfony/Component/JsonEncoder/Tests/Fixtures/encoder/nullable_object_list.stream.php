<?php

return static function (mixed $data, \Psr\Container\ContainerInterface $normalizers, array $options): \Traversable {
    if (\is_array($data)) {
        yield '[';
        $prefix = '';
        foreach ($data as $value) {
            yield $prefix;
            yield '{"@id":';
            yield \json_encode($value->id);
            yield ',"name":';
            yield \json_encode($value->name);
            yield '}';
            $prefix = ',';
        }
        yield ']';
    } elseif (null === $data) {
        yield 'null';
    } else {
        throw new \Symfony\Component\JsonEncoder\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value.', \get_debug_type($data)));
    }
};
