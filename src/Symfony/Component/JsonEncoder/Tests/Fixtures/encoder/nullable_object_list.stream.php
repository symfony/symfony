<?php

return static function (mixed $data, \Symfony\Component\JsonEncoder\Stream\StreamWriterInterface $stream, array $config, ?\Psr\Container\ContainerInterface $services): void {
    if (\is_array($data)) {
        $stream->write('[');
        $prefix_0 = '';
        foreach ($data as $value_0) {
            $stream->write($prefix_0);
            $stream->write('{"@id":');
            $stream->write(\json_encode($value_0->id));
            $stream->write(',"name":');
            $stream->write(\json_encode($value_0->name));
            $stream->write('}');
            $prefix_0 = ',';
        }
        $stream->write(']');
    } elseif (null === $data) {
        $stream->write('null');
    } else {
        throw new \Symfony\Component\JsonEncoder\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value.', \get_debug_type($data)));
    }
};
