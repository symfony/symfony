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

use Symfony\Component\AutoMapper\AutoMapperRegistryInterface;
use Symfony\Component\AutoMapper\MapperMetadataInterface;
use Symfony\Component\PropertyInfo\Type;

/**
 * @expiremental in 4.3
 *
 * @author Joel Wurtz <jwurtz@jolicode.com>
 */
final class ObjectTransformerFactory extends AbstractUniqueTypeTransformerFactory
{
    private $autoMapper;

    public function __construct(AutoMapperRegistryInterface $autoMapper)
    {
        $this->autoMapper = $autoMapper;
    }

    /**
     * {@inheritdoc}
     */
    protected function createTransformer(Type $sourceType, Type $targetType, MapperMetadataInterface $mapperMetadata): ?TransformerInterface
    {
        // Only deal with source type being an object or an array that is not a collection
        if (!$this->isObjectType($sourceType) || !$this->isObjectType($targetType)) {
            return null;
        }

        $sourceTypeName = 'array';
        $targetTypeName = 'array';

        if (Type::BUILTIN_TYPE_OBJECT === $sourceType->getBuiltinType()) {
            $sourceTypeName = $sourceType->getClassName();
        }

        if (Type::BUILTIN_TYPE_OBJECT === $targetType->getBuiltinType()) {
            $targetTypeName = $targetType->getClassName();
        }

        if ($this->autoMapper->hasMapper($sourceTypeName, $targetTypeName)) {
            return new ObjectTransformer($sourceType, $targetType);
        }

        return null;
    }

    private function isObjectType(Type $type): bool
    {
        return
            Type::BUILTIN_TYPE_OBJECT === $type->getBuiltinType()
            ||
            (Type::BUILTIN_TYPE_ARRAY === $type->getBuiltinType() && !$type->isCollection())
        ;
    }
}
