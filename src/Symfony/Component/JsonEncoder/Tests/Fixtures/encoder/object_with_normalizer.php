<?php

return static function (mixed $data, \Psr\Container\ContainerInterface $normalizers, array $options): \Traversable {
    yield '{"id":';
    yield \json_encode($normalizers->get('Symfony\Component\JsonEncoder\Tests\Fixtures\Normalizer\DoubleIntAndCastToStringNormalizer')->normalize($data->id, $options));
    yield ',"active":';
    yield \json_encode($normalizers->get('Symfony\Component\JsonEncoder\Tests\Fixtures\Normalizer\BooleanStringNormalizer')->normalize($data->active, $options));
    yield ',"name":';
    yield \json_encode(strtolower($data->name));
    yield ',"range":';
    yield \json_encode(Symfony\Component\JsonEncoder\Tests\Fixtures\Model\DummyWithNormalizerAttributes::concatRange($data->range, $options));
    yield '}';
};
