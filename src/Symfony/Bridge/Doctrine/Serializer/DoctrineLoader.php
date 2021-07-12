<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Bridge\Doctrine\Serializer;

use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Serializer\Mapping\AttributeMetadata;
use Symfony\Component\Serializer\Mapping\ClassMetadataInterface;
use Symfony\Component\Serializer\Mapping\Loader\LoaderInterface;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;

/**
 * Sets DateTimeNormalizer::FORMAT_KEY to ISO 8601 (Y-m-d) for dates, (H:i:s) for time.
 */
class DoctrineLoader implements LoaderInterface
{
    private $doctrine;
    private $dateFormat;
    private $timeFormat;

    public function __construct(ManagerRegistry $doctrine, string $dateFormat = 'Y-m-d', string $timeFormat = 'H:i:s')
    {
        $this->doctrine = $doctrine;
        $this->dateFormat = $dateFormat;
        $this->timeFormat = $timeFormat;
    }

    public function loadClassMetadata(ClassMetadataInterface $classMetadata): bool
    {
        $reflectionClass = $classMetadata->getReflectionClass();
        $entityManager = $this->doctrine->getManagerForClass($reflectionClass->getName());

        if (null === $entityManager) {
            return false;
        }

        $doctrineMetadata = $entityManager->getClassMetadata($reflectionClass->getName());

        $attributesMetadata = $classMetadata->getAttributesMetadata();

        foreach ($reflectionClass->getProperties() as $property) {
            if (!$doctrineMetadata->hasField($property->name)) {
                continue;
            }

            $context = $this->getContext($doctrineMetadata->getTypeOfField($property->name));

            if (null !== $context) {
                $attributeMetadata = $attributesMetadata[$property->name] ?? new AttributeMetadata($property->name);
                $classMetadata->addAttributeMetadata($attributeMetadata);

                $attributeMetadata->setNormalizationContextForGroups($context);
                $attributeMetadata->setDenormalizationContextForGroups($context);
            }
        }

        return true;
    }

    private function getContext(string $type): ?array
    {
        switch ($type) {
            case Types::DATE_IMMUTABLE:
            case Types::DATE_MUTABLE:
                return [
                    DateTimeNormalizer::FORMAT_KEY => $this->dateFormat,
                ];
            case Types::TIME_IMMUTABLE:
            case Types::TIME_MUTABLE:
                return [
                    DateTimeNormalizer::FORMAT_KEY => $this->timeFormat,
                ];
            default:
                return null;
        }
    }
}
