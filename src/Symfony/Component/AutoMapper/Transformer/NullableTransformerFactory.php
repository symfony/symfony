<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\AutoMapper\Transformer;

use Symfony\Component\AutoMapper\MapperMetadataInterface;
use Symfony\Component\PropertyInfo\Type;

/**
 * @expiremental in 4.3
 *
 * @author Joel Wurtz <jwurtz@jolicode.com>
 */
final class NullableTransformerFactory implements TransformerFactoryInterface
{
    private $chainTransformerFactory;

    public function __construct(ChainTransformerFactory $chainTransformerFactory)
    {
        $this->chainTransformerFactory = $chainTransformerFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function getTransformer(?array $sourcesTypes, ?array $targetTypes, MapperMetadataInterface $mapperMetadata): ?TransformerInterface
    {
        $nbSourcesTypes = $sourcesTypes ? \count($sourcesTypes) : 0;

        if (null === $sourcesTypes || 0 === $nbSourcesTypes || $nbSourcesTypes > 1) {
            return null;
        }

        /** @var Type $propertyType */
        $propertyType = $sourcesTypes[0];

        if (null === $propertyType) {
            var_dump($sourcesTypes);
        }

        if (!$propertyType->isNullable()) {
            return null;
        }

        $isTargetNullable = false;

        foreach ($targetTypes as $targetType) {
            if ($targetType->isNullable()) {
                $isTargetNullable = true;

                break;
            }
        }

        $subTransformer = $this->chainTransformerFactory->getTransformer([new Type(
            $propertyType->getBuiltinType(),
            false,
            $propertyType->getClassName(),
            $propertyType->isCollection(),
            $propertyType->getCollectionKeyType(),
            $propertyType->getCollectionValueType()
        )], $targetTypes, $mapperMetadata);

        if (null === $subTransformer) {
            return null;
        }

        // Remove nullable property here to avoid infinite loop
        return new NullableTransformer($subTransformer, $isTargetNullable);
    }
}
