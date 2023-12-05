<?php

namespace Symfony\Serializer\Normalizer;

use Symfony\Component\Serializer\Tests\Fixtures\CustomNormalizer\FullTypeHints\InheritanceChild;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class Symfony_Component_Serializer_Tests_Fixtures_CustomNormalizer_FullTypeHints_InheritanceChild implements NormalizerInterface, DenormalizerInterface
{
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
            'childCute' => $object->getChildCute(),
            'cute' => $object->isCute(),
            'childName' => $object->childName,
            'name' => $object->name,
            'childAge' => $object->getChildAge(),
            'childHeight' => $object->getChildHeight(),
            'age' => $object->getAge(),
            'height' => $object->getHeight(),
            'handsome' => $object->isHandsome(),
        ];
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
