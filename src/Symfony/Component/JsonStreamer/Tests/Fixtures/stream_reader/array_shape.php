<?php

/**
 * @return array{'id': int, 'name': string}
 */
return static function (string|\Stringable $string, \Psr\Container\ContainerInterface $transformers, array $options): mixed {
    return \Symfony\Component\JsonStreamer\Read\Decoder::decodeString((string) $string);
};
