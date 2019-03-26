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
 * Create a decorated transformer to handle array type.
 *
 * @expiremental in 4.3
 *
 * @author Joel Wurtz <jwurtz@jolicode.com>
 */
final class ArrayTransformerFactory extends AbstractUniqueTypeTransformerFactory
{
    private $chainTransformerFactory;

    public function __construct(ChainTransformerFactory $chainTransformerFactory)
    {
        $this->chainTransformerFactory = $chainTransformerFactory;
    }

    /**
     * {@inheritdoc}
     */
    protected function createTransformer(Type $sourceType, Type $targetType, MapperMetadataInterface $mapperMetadata): ?TransformerInterface
    {
        if (!$sourceType->isCollection()) {
            return null;
        }

        if (!$targetType->isCollection()) {
            return null;
        }

        if (null === $sourceType->getCollectionValueType() || null === $targetType->getCollectionValueType()) {
            $subItemTransformer = new CopyTransformer();
        } else {
            $subItemTransformer = $this->chainTransformerFactory->getTransformer([$sourceType->getCollectionValueType()], [$targetType->getCollectionValueType()], $mapperMetadata);
        }

        if (null !== $subItemTransformer) {
            return new ArrayTransformer($subItemTransformer);
        }

        return null;
    }
}
