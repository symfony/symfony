<?php

/**
 * @return int
 */
return static function (string|\Stringable $string, \Psr\Container\ContainerInterface $transformers, \Symfony\Component\JsonStreamer\Read\Instantiator $instantiator, array $options): mixed {
    return \Symfony\Component\JsonStreamer\Read\Decoder::decodeString((string) $string);
};
