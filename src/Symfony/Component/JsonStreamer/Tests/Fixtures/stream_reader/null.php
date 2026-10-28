<?php

declare(strict_types=1);

/**
 * @return null
 */
return static function (string|\Stringable $string, \Psr\Container\ContainerInterface $valueTransformers, \Symfony\Component\JsonStreamer\Read\Instantiator $instantiator, array $options): mixed {
    return \Symfony\Component\JsonStreamer\Read\Decoder::decodeString((string) $string);
};
