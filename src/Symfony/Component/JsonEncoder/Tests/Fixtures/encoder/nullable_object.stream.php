<?php

return static function (mixed $data, \Symfony\Component\JsonEncoder\Stream\StreamWriterInterface $stream, array $config, ?\Psr\Container\ContainerInterface $services): void {
    if ($data instanceof \Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNameAttributes) {
        $stream->write('{"@id":');
        $stream->write(\json_encode($data->id));
        $stream->write(',"name":');
        $stream->write(\json_encode($data->name));
        $stream->write('}');
    } elseif (null === $data) {
        $stream->write('null');
    } else {
        throw new \Symfony\Component\JsonEncoder\Exception\UnexpectedValueException(\sprintf('Unexpected "%s" value.', \get_debug_type($data)));
    }
};
