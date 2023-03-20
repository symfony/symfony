<?php

return static function (mixed $data, array $config, ?\Psr\Container\ContainerInterface $services): \Traversable {
    if (\is_array($data)) {
        yield '{';
        $prefix_0 = '';
        foreach ($data as $key_0 => $value_0) {
            $key_0 = \substr(\json_encode($key_0), 1, -1);
            yield "{$prefix_0}\"{$key_0}\":";
            yield '{"@id":';
            yield \json_encode($value_0->id);
            yield ',"name":';
            yield \json_encode($value_0->name);
            yield '}';
            $prefix_0 = ',';
        }
        yield '}';
    } elseif (null === $data) {
        yield 'null';
    } else {
        throw new \Symfony\Component\JsonEncoder\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value.', \get_debug_type($data)));
    }
};
