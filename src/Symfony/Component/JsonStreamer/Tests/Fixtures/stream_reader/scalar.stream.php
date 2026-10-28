<?php

declare(strict_types=1);

/**
 * @return int
 */
return static function (mixed $stream, \Psr\Container\ContainerInterface $transformers, \Symfony\Component\JsonStreamer\Read\LazyInstantiator $instantiator, array $options): mixed {
    return (\Symfony\Component\JsonStreamer\Read\Decoder::decodeStream($stream, 0, null) ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "int".'));
};
