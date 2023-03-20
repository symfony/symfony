<?php

return static function (string|\Stringable $string, \Psr\Container\ContainerInterface $denormalizers, \Symfony\Component\JsonEncoder\Decode\Instantiator $instantiator, array $options): mixed {
    return \Symfony\Component\JsonEncoder\Decode\NativeDecoder::decodeString((string) $string);
};
