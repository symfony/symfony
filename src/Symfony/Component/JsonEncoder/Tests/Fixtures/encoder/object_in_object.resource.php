<?php

return static function (mixed $data, mixed $stream, array $config, ?\Psr\Container\ContainerInterface $services): void {
    \fwrite($stream, '{"name":');
    \fwrite($stream, \json_encode($data->name));
    \fwrite($stream, ',"otherDummyOne":{"@id":');
    \fwrite($stream, \json_encode($data->otherDummyOne->id));
    \fwrite($stream, ',"name":');
    \fwrite($stream, \json_encode($data->otherDummyOne->name));
    \fwrite($stream, '},"otherDummyTwo":');
    \fwrite($stream, \json_encode($data->otherDummyTwo));
    \fwrite($stream, '}');
};
