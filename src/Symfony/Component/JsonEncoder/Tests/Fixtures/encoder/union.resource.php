<?php

return static function (mixed $data, mixed $stream, array $config, ?\Psr\Container\ContainerInterface $services): void {
    if (\is_array($data)) {
        \fwrite($stream, '[');
        $prefix_0 = '';
        foreach ($data as $value_0) {
            \fwrite($stream, $prefix_0);
            \fwrite($stream, \json_encode($value_0->value));
            $prefix_0 = ',';
        }
        \fwrite($stream, ']');
    } elseif ($data instanceof \Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNameAttributes) {
        \fwrite($stream, '{"@id":');
        \fwrite($stream, \json_encode($data->id));
        \fwrite($stream, ',"name":');
        \fwrite($stream, \json_encode($data->name));
        \fwrite($stream, '}');
    } elseif (\is_int($data)) {
        \fwrite($stream, \json_encode($data));
    } else {
        throw new \Symfony\Component\JsonEncoder\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value.', \get_debug_type($data)));
    }
};
