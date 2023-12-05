<?php

namespace Symfony\Serializer\Normalizer;

use Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\NoTypeHints\InheritanceChild;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;

class Symfony_Component_Serializer_Tests_Fixtures_CustomNormalizer_NoTypeHints_InheritanceChild implements NormalizerInterface, DenormalizerInterface, NormalizerAwareInterface
{
    private null|NormalizerInterface $normalizer = NULL;

    public function getSupportedTypes(?string $format): array
    {
        return [InheritanceChild::class => true];
    }

    public function supportsNormalization(mixed $data, ?string $format = NULL, array $context = []): bool
    {
        return $data instanceof InheritanceChild;
    }

    public function supportsDenormalization(mixed $data, string $type, ?string $format = NULL, array $context = []): bool
    {
        return $type === InheritanceChild::class;
    }

    /**
     * @param InheritanceChild $object
     */
    public function normalize(mixed $object, ?string $format = NULL, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        return [
            'childCute' => $this->normalizeChild($object->getChildCute(), $format, $context, true),
            'cute' => $this->normalizeChild($object->getCute(), $format, $context, true),
            'childName' => $this->normalizeChild($object->childName, $format, $context, true),
            'name' => $this->normalizeChild($object->name, $format, $context, true),
            'childAge' => $this->normalizeChild($object->getChildAge(), $format, $context, true),
            'childHeight' => $this->normalizeChild($object->getChildHeight(), $format, $context, true),
            'age' => $this->normalizeChild($object->getAge(), $format, $context, true),
            'height' => $this->normalizeChild($object->getHeight(), $format, $context, true),
            'handsome' => $this->normalizeChild($object->getHandsome(), $format, $context, true),
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
        
        $output = new InheritanceChild(
            $data['childCute'],
            $data['cute'],
        );
        if (array_key_exists('childName', $data)) {
            $output->childName = $data['childName'];
        }
        if (array_key_exists('name', $data)) {
            $output->name = $data['name'];
        }
        if (array_key_exists('childAge', $data)) {
            $output->setChildAge($data['childAge']);
        }
        if (array_key_exists('childHeight', $data)) {
            $output->setChildHeight($data['childHeight']);
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
        
        return $output;
    }

}
