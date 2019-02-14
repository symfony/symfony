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
 * Abstract transformer which is used by transformer needing transforming only from one single type to one single type.
 *
 * @author Joel Wurtz <jwurtz@jolicode.com>
 */
abstract class AbstractUniqueTypeTransformerFactory implements TransformerFactoryInterface
{
    /**
     * {@inheritdoc}
     */
    public function getTransformer(?array $sourcesTypes, ?array $targetTypes, MapperMetadataInterface $mapperMetadata): ?TransformerInterface
    {
        $nbSourcesTypes = $sourcesTypes ? \count($sourcesTypes) : 0;
        $nbTargetsTypes = $targetTypes ? \count($targetTypes) : 0;

        if (0 === $nbSourcesTypes || $nbSourcesTypes > 1 || !$sourcesTypes[0] instanceof Type) {
            return null;
        }

        if (0 === $nbTargetsTypes || $nbTargetsTypes > 1 || !$targetTypes[0] instanceof Type) {
            return null;
        }

        return $this->createTransformer($sourcesTypes[0], $targetTypes[0], $mapperMetadata);
    }

    abstract protected function createTransformer(Type $sourceType, Type $targetType, MapperMetadataInterface $mapperMetadata): ?TransformerInterface;
}
