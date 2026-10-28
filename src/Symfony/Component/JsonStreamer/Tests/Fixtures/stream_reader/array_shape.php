<?php

declare(strict_types=1);

/**
 * @return array{'id': int, 'name': string}
 */
return static function (string|\Stringable $string, \Psr\Container\ContainerInterface $transformers, \Symfony\Component\JsonStreamer\Read\Instantiator $instantiator, array $options): mixed {
    return (\Symfony\Component\JsonStreamer\Read\Decoder::decodeString((string) $string) ?? throw new \Symfony\Component\JsonStreamer\Exception\UnexpectedValueException('Unexpected "null" value for "array{\'id\': int, \'name\': string}".'));
};
