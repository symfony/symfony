<?php

return static function (mixed $data, \Symfony\Component\JsonEncoder\Stream\StreamWriterInterface $stream, array $config, ?\Psr\Container\ContainerInterface $services): void {
    $stream->write('{"name":');
    $stream->write(\json_encode($data->name));
    $stream->write(',"otherDummyOne":{"@id":');
    $stream->write(\json_encode($data->otherDummyOne->id));
    $stream->write(',"name":');
    $stream->write(\json_encode($data->otherDummyOne->name));
    $stream->write('},"otherDummyTwo":');
    $stream->write(\json_encode($data->otherDummyTwo));
    $stream->write('}');
};
