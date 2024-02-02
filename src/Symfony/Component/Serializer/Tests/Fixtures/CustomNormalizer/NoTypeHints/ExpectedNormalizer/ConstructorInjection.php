<?php

namespace Symfony\Serializer\Normalizer;

use Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\NoTypeHints\ConstructorInjection;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
class Symfony_Component_Serializer_Tests_Fixtures_CustomNormalizer_NoTypeHints_ConstructorInjection implements NormalizerInterface, DenormalizerInterface, NormalizerAwareInterface
{
    private null|NormalizerInterface $normalizer = null;
    public function getSupportedTypes(?string $format): array
    {
        return [ConstructorInjection::class => true];
    }
    public function supportsNormalization(mixed $data, string $format = null, array $context = []): bool
    {
        return $data instanceof ConstructorInjection;
    }
    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool
    {
        return $type === ConstructorInjection::class;
    }
    /**
    * @param ConstructorInjection $object
    */
    public function normalize(mixed $object, string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        return ['name' => $this->normalizeChild($object->getName(), $format, $context, true), 'age' => $this->normalizeChild($object->getAge(), $format, $context, true), 'height' => $this->normalizeChild($object->getHeight(), $format, $context, true), 'handsome' => $this->normalizeChild($object->getHandsome(), $format, $context, true), 'nameOfFriends' => $this->normalizeChild($object->getNameOfFriends(), $format, $context, true), 'picture' => $this->normalizeChild($object->getPicture(), $format, $context, true), 'pet' => $this->normalizeChild($object->getPet(), $format, $context, true), 'relation' => $this->normalizeChild($object->getRelation(), $format, $context, true), 'notSet' => $this->normalizeChild($object->getNotSet(), $format, $context, true)];
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
        if ($canBeIterable && is_iterable($object)) {
            return array_map(fn($item) => $this->normalizeChild($item, $format, $context, true), $object);
        }
        return $this->normalizer->normalize($object, $format, $context);
    }
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        $data = (array) $data;
        $output = new ConstructorInjection($data['name'], $data['age'], $data['height'], $data['handsome'], $data['nameOfFriends'], $data['picture'], $data['pet'], $data['relation']);
        return $output;
    }
}