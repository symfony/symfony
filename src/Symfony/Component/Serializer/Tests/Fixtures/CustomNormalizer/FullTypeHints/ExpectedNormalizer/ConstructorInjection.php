<?php

namespace Symfony\Serializer\Normalizer;

use Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\FullTypeHints\ConstructorInjection;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Exception\DenormalizingUnionFailedException;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;

class Symfony_Component_Serializer_Tests_Fixtures_CustomNormalizer_FullTypeHints_ConstructorInjection implements NormalizerInterface, DenormalizerInterface, NormalizerAwareInterface, DenormalizerAwareInterface
{
    private null|NormalizerInterface $normalizer = NULL;
    private null|DenormalizerInterface $denormalizer = NULL;

    public function getSupportedTypes(?string $format): array
    {
        return [ConstructorInjection::class => true];
    }

    public function supportsNormalization(mixed $data, ?string $format = NULL, array $context = []): bool
    {
        return $data instanceof ConstructorInjection;
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = NULL, array $context = []): bool
    {
        return $type === ConstructorInjection::class;
    }

    /**
     * @param ConstructorInjection $object
     */
    public function normalize(mixed $object, ?string $format = NULL, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        return [
            'name' => $object->getName(),
            'age' => $object->getAge(),
            'height' => $object->getHeight(),
            'handsome' => $object->isHandsome(),
            'nameOfFriends' => $this->normalizeChild($object->getNameOfFriends(), $format, $context, true),
            'picture' => $this->normalizeChild($object->getPicture(), $format, $context, true),
            'pet' => $object->getPet(),
            'relation' => $this->normalizeChild($object->getRelation(), $format, $context, false),
            'notSet' => $object->getNotSet(),
        ];
    }

    public function setNormalizer(NormalizerInterface $normalizer): void
    {
        $this->normalizer = $normalizer;
    }

    private function normalizeChild(mixed $object, ?string $format, array $context, bool $canBeIterable): mixed
    {
        if (is_scalar($object) || null === $object) {
            return $object;
        }
        
        if ($canBeIterable === true && is_iterable($object)) {
            return array_map(fn($item) => $this->normalizeChild($item, $format, $context, true), $object);
        }
        
        return $this->normalizer->normalize($object, $format, $context);
        
    }

    public function denormalize(mixed $data, string $type, ?string $format = NULL, array $context = []): mixed
    {
        $argument7 = $this->denormalizeChild($data['relation'], \Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\FullTypeHints\DummyObject::class, $format, $context, false);
        
        $output = new ConstructorInjection(
            $data['name'],
            $data['age'],
            $data['height'],
            $data['handsome'],
            $data['nameOfFriends'],
            $data['picture'],
            $data['pet'],
            $argument7,
        );
        
        return $output;
    }

    public function setDenormalizer(DenormalizerInterface $denormalizer): void
    {
        $this->denormalizer = $denormalizer;
    }

    private function denormalizeChild(mixed $data, string $type, ?string $format, array $context, bool $canBeIterable): mixed
    {
        if (is_scalar($data) || null === $data) {
            return $data;
        }
        
        if ($canBeIterable === true && is_iterable($data)) {
            return array_map(fn($item) => $this->denormalizeChild($item, $type, $format, $context, true), $data);
        }
        
        return $this->denormalizer->denormalize($data, $type, $format, $context);
        
    }

}
