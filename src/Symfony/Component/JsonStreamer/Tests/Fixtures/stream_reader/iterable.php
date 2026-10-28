<?php

declare(strict_types=1);

/**
 * @return iterable
 */
return static function (string|\Stringable $string, \Psr\Container\ContainerInterface $transformers, array $options): mixed {
    return (\Symfony\Component\JsonStreamer\Read\Decoder::decodeString((string) $string) ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "iterable".'));
};
