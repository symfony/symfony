<?php

namespace Symfony\Serializer\Normalizer;

use Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\FullTypeHints\PrivateConstructor;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
class Symfony_Component_Serializer_Tests_Fixtures_CustomNormalizer_FullTypeHints_PrivateConstructor implements NormalizerInterface, DenormalizerInterface
{
    public function getSupportedTypes(?string $format): array
    {
        return [PrivateConstructor::class => true];
    }
    public function supportsNormalization(mixed $data, string $format = null, array $context = []): bool
    {
        return $data instanceof PrivateConstructor;
    }
    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool
    {
        return $type === PrivateConstructor::class;
    }
    /**
    * @param PrivateConstructor $object
    */
    public function normalize(mixed $object, string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        return ['foo' => $object->foo];
    }
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        $data = (array) $data;
        $output = (new \ReflectionClass(PrivateConstructor::class))->newInstanceWithoutConstructor();
        if (array_key_exists('foo', $data)) {
            $output->foo = $data['foo'];
        }
        return $output;
    }
}