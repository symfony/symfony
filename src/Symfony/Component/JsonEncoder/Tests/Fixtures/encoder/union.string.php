<?php

return static function (mixed $data, array $config, ?\Psr\Container\ContainerInterface $services): \Traversable {
    if (\is_array($data)) {
        yield '[';
        $prefix_0 = '';
        foreach ($data as $value_0) {
            yield $prefix_0;
            yield \json_encode($value_0->value);
            $prefix_0 = ',';
        }
        yield ']';
    } elseif ($data instanceof \Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNameAttributes) {
        yield '{"@id":';
        yield \json_encode($data->id);
        yield ',"name":';
        yield \json_encode($data->name);
        yield '}';
    } elseif (\is_int($data)) {
        yield \json_encode($data);
    } else {
        throw new \Symfony\Component\JsonEncoder\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value.', \get_debug_type($data)));
    }
};
