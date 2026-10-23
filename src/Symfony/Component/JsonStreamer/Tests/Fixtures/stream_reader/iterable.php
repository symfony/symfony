<?php

/**
 * @return iterable
 */
return static function (string|\Stringable $string, \Psr\Container\ContainerInterface $transformers, array $options): mixed {
    return \Symfony\Component\JsonStreamer\Read\Decoder::decodeString((string) $string);
};
