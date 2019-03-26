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
final class BuiltinTransformerFactory implements TransformerFactoryInterface
{
    private const BUILTIN = [
        Type::BUILTIN_TYPE_BOOL,
        Type::BUILTIN_TYPE_CALLABLE,
        Type::BUILTIN_TYPE_FLOAT,
        Type::BUILTIN_TYPE_INT,
        Type::BUILTIN_TYPE_ITERABLE,
        Type::BUILTIN_TYPE_NULL,
        Type::BUILTIN_TYPE_RESOURCE,
        Type::BUILTIN_TYPE_STRING,
    ];

    public function getTransformer(?array $sourcesTypes, ?array $targetTypes, MapperMetadataInterface $mapperMetadata): ?TransformerInterface
    {
        $nbSourcesTypes = $sourcesTypes ? \count($sourcesTypes) : 0;

        if (null === $sourcesTypes || 0 === $nbSourcesTypes || $nbSourcesTypes > 1 || !$sourcesTypes[0] instanceof Type) {
            return null;
        }

        /** @var Type $propertyType */
        $propertyType = $sourcesTypes[0];

        if (\in_array($propertyType->getBuiltinType(), self::BUILTIN, true)) {
            return new BuiltinTransformer($propertyType, $targetTypes);
        }

        return null;
    }
}
