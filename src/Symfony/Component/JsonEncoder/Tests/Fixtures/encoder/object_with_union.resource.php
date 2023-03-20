<?php

return static function (mixed $data, mixed $stream, array $config, ?\Psr\Container\ContainerInterface $services): void {
    \fwrite($stream, '{"value":');
    if ($data->value instanceof \Symfony\Component\JsonEncoder\Tests\Fixtures\Enum\DummyBackedEnum) {
        \fwrite($stream, \json_encode($data->value->value));
    } elseif (null === $data->value) {
        \fwrite($stream, 'null');
    } elseif (\is_string($data->value)) {
        \fwrite($stream, \json_encode($data->value));
    } else {
        throw new \Symfony\Component\JsonEncoder\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value.', \get_debug_type($data->value)));
    }
    \fwrite($stream, '}');
};
