<?php

namespace Symfony\Serializer\Normalizer;

use Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\FullTypeHints\SetterInjection;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Exception\DenormalizingUnionFailedException;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
class Symfony_Component_Serializer_Tests_Fixtures_CustomNormalizer_FullTypeHints_SetterInjection implements NormalizerInterface, DenormalizerInterface, NormalizerAwareInterface, DenormalizerAwareInterface
{
    private null|NormalizerInterface $normalizer = null;
    private null|DenormalizerInterface $denormalizer = null;
    public function getSupportedTypes(?string $format): array
    {
        return [SetterInjection::class => true];
    }
    public function supportsNormalization(mixed $data, string $format = null, array $context = []): bool
    {
        return $data instanceof SetterInjection;
    }
    public function supportsDenormalization(mixed $data, string $type, string $format = null, array $context = []): bool
    {
        return $type === SetterInjection::class;
    }
    /**
    * @param SetterInjection $object
    */
    public function normalize(mixed $object, string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        return ['name' => $object->getName(), 'age' => $object->getAge(), 'height' => $object->getHeight(), 'handsome' => $object->isHandsome(), 'nameOfFriends' => $this->normalizeChild($object->getNameOfFriends(), $format, $context, true), 'picture' => $this->normalizeChild($object->getPicture(), $format, $context, true), 'pet' => $object->getPet(), 'relation' => $this->normalizeChild($object->getRelation(), $format, $context, false), 'notSet' => $object->getNotSet()];
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
        $output = new SetterInjection();
        if (array_key_exists('name', $data)) {
            $output->setName($data['name']);
        }
        if (array_key_exists('age', $data)) {
            $output->setAge($data['age']);
        }
        if (array_key_exists('height', $data)) {
            $output->setHeight($data['height']);
        }
        if (array_key_exists('handsome', $data)) {
            $output->setHandsome($data['handsome']);
        }
        if (array_key_exists('nameOfFriends', $data)) {
            $output->setNameOfFriends($data['nameOfFriends']);
        }
        if (array_key_exists('picture', $data)) {
            $output->setPicture($data['picture']);
        }
        if (array_key_exists('pet', $data)) {
            $output->setPet($data['pet']);
        }
        if (array_key_exists('relation', $data)) {
            $setter0 = $this->denormalizeChild($data['relation'], \Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\FullTypeHints\DummyObject::class, $format, $context, false);
            $output->setRelation($setter0);
        }
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
        if ($canBeIterable && is_iterable($data)) {
            return array_map(fn($item) => $this->denormalizeChild($item, $type, $format, $context, true), $data);
        }
        return $this->denormalizer->denormalize($data, $type, $format, $context);
    }
}