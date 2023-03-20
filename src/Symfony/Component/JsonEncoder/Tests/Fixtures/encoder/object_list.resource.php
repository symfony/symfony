<?php

return static function (mixed $data, mixed $stream, array $config, ?\Psr\Container\ContainerInterface $services): void {
    \fwrite($stream, '[');
    $prefix_0 = '';
    foreach ($data as $value_0) {
        \fwrite($stream, $prefix_0);
        \fwrite($stream, '{"@id":');
        \fwrite($stream, \json_encode($value_0->id));
        \fwrite($stream, ',"name":');
        \fwrite($stream, \json_encode($value_0->name));
        \fwrite($stream, '}');
        $prefix_0 = ',';
    }
    \fwrite($stream, ']');
};
