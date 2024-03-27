<?php

return static function (mixed $data, \Symfony\Component\JsonEncoder\Stream\StreamWriterInterface $stream, array $config, ?\Psr\Container\ContainerInterface $services): void {
    if (\is_array($data)) {
        $stream->write('[');
        $prefix_0 = '';
        foreach ($data as $value_0) {
            $stream->write($prefix_0);
            $stream->write(\json_encode($value_0->value));
            $prefix_0 = ',';
        }
        $stream->write(']');
    } elseif ($data instanceof \Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNameAttributes) {
        $stream->write('{"@id":');
        $stream->write(\json_encode($data->id));
        $stream->write(',"name":');
        $stream->write(\json_encode($data->name));
        $stream->write('}');
    } elseif (\is_int($data)) {
        $stream->write(\json_encode($data));
    } else {
        throw new \Symfony\Component\JsonEncoder\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value.', \get_debug_type($data)));
    }
};
