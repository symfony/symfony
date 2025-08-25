<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper;

use Psr\Container\ContainerInterface;
use Symfony\Component\ObjectMapper\Exception\NoSuchPropertyException;
use Symfony\Component\ObjectMapper\Metadata\ObjectMapperMetadataFactoryInterface;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException as PropertyAccessorNoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Helper class for cached mapping functions.
 *
 * @internal
 */
final class MappingHelper
{
    public static function getCallable(string|callable $fn, ?ContainerInterface $locator = null): ?callable
    {
        if (\is_callable($fn)) {
            return $fn;
        }

        if ($locator?->has($fn)) {
            return $locator->get($fn);
        }

        return null;
    }

    public static function call(callable $fn, mixed $value, object $source, ?object $target = null): mixed
    {
        if (\is_string($fn)) {
            return \call_user_func($fn, $value);
        }

        return $fn($value, $source, $target);
    }

    public static function getValue(object $source, string $propertyName, ?PropertyAccessorInterface $propertyAccessor = null): mixed
    {
        if ($propertyAccessor) {
            try {
                return $propertyAccessor->getValue($source, $propertyName);
            } catch (PropertyAccessorNoSuchPropertyException $e) {
                throw new NoSuchPropertyException($e->getMessage(), $e->getCode(), $e);
            }
        }

        if (!property_exists($source, $propertyName) && !isset($source->{$propertyName})) {
            throw new NoSuchPropertyException(\sprintf('The property "%s" does not exist on "%s".', $propertyName, get_debug_type($source)));
        }

        return $source->{$propertyName};
    }

    public static function setValue(object $target, string $propertyName, mixed $value, ?PropertyAccessorInterface $propertyAccessor = null): void
    {
        if ($propertyAccessor) {
            $propertyAccessor->setValue($target, $propertyName, $value);
        } else {
            $target->{$propertyName} = $value;
        }
    }

    public static function hasMappingTarget(object $target, ObjectMapperMetadataFactoryInterface $metadataFactory): bool
    {
        $metadata = $metadataFactory->create($target);
        foreach ($metadata as $mapping) {
            if ($mapping->target && \is_string($mapping->target) && class_exists($mapping->target)) {
                return true;
            }
        }

        return false;
    }
}
