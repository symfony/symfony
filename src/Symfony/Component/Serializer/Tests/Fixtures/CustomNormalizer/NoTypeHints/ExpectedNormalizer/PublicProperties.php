<?php

namespace Symfony\Serializer\Normalizer;

use Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\NoTypeHints\PublicProperties;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
class Symfony_Component_Serializer_Tests_Fixtures_CustomNormalizer_NoTypeHints_PublicProperties implements NormalizerInterface, DenormalizerInterface, NormalizerAwareInterface
{
    private null|NormalizerInterface $normalizer = null;
    public function getSupportedTypes(?string $format): array
    {
        return [PublicProperties::class => true];
    }
    public function supportsNormalization(mixed $data, string $format = null, array $context = []): bool
    {
        return $data instanceof PublicProperties;
    }
    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool
    {
        return $type === PublicProperties::class;
    }
    /**
    * @param PublicProperties $object
    */
    public function normalize(mixed $object, string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        return ['name' => $this->normalizeChild($object->name, $format, $context, true), 'age' => $this->normalizeChild($object->age, $format, $context, true), 'height' => $this->normalizeChild($object->height, $format, $context, true), 'handsome' => $this->normalizeChild($object->handsome, $format, $context, true), 'nameOfFriends' => $this->normalizeChild($object->nameOfFriends, $format, $context, true), 'picture' => $this->normalizeChild($object->picture, $format, $context, true), 'pet' => $this->normalizeChild($object->pet, $format, $context, true), 'relation' => $this->normalizeChild($object->relation, $format, $context, true), 'notSet' => $this->normalizeChild($object->notSet, $format, $context, true)];
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
        $output = new PublicProperties();
        if (array_key_exists('name', $data)) {
            $output->name = $data['name'];
        }
        if (array_key_exists('age', $data)) {
            $output->age = $data['age'];
        }
        if (array_key_exists('height', $data)) {
            $output->height = $data['height'];
        }
        if (array_key_exists('handsome', $data)) {
            $output->handsome = $data['handsome'];
        }
        if (array_key_exists('nameOfFriends', $data)) {
            $output->nameOfFriends = $data['nameOfFriends'];
        }
        if (array_key_exists('picture', $data)) {
            $output->picture = $data['picture'];
        }
        if (array_key_exists('pet', $data)) {
            $output->pet = $data['pet'];
        }
        if (array_key_exists('relation', $data)) {
            $output->relation = $data['relation'];
        }
        if (array_key_exists('notSet', $data)) {
            $output->notSet = $data['notSet'];
        }
        return $output;
    }
}