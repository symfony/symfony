<?php

namespace Symfony\Serializer\Normalizer;

use Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\FullTypeHints\ExtraSetter;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
class Symfony_Component_Serializer_Tests_Fixtures_CustomNormalizer_FullTypeHints_ExtraSetter implements NormalizerInterface, DenormalizerInterface
{
    public function getSupportedTypes(?string $format): array
    {
        return [ExtraSetter::class => true];
    }
    public function supportsNormalization(mixed $data, string $format = null, array $context = []): bool
    {
        return $data instanceof ExtraSetter;
    }
    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool
    {
        return $type === ExtraSetter::class;
    }
    /**
    * @param ExtraSetter $object
    */
    public function normalize(mixed $object, string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        return ['name' => $object->getName(), 'age' => $object->getAge()];
    }
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        $output = new ExtraSetter($data['name']);
        if (array_key_exists('age', $data)) {
            $output->setAge($data['age']);
        }
        return $output;
    }
}