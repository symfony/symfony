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
final class DateTimeTransformerFactory extends AbstractUniqueTypeTransformerFactory
{
    /**
     * {@inheritdoc}
     */
    protected function createTransformer(Type $sourceType, Type $targetType, MapperMetadataInterface $mapperMetadata): ?TransformerInterface
    {
        $isSourceDate = $this->isDateTimeType($sourceType);
        $isTargetDate = $this->isDateTimeType($targetType);

        if ($isSourceDate && $isTargetDate) {
            return $this->createTransformerForSourceAndTarget($sourceType, $targetType);
        }

        if ($isSourceDate) {
            return $this->createTransformerForSource($targetType, $mapperMetadata);
        }

        if ($isTargetDate) {
            return $this->createTransformerForTarget($sourceType, $targetType, $mapperMetadata);
        }

        return null;
    }

    protected function createTransformerForSourceAndTarget(Type $sourceType, Type $targetType): ?TransformerInterface
    {
        $isSourceMutable = $this->isDateTimeMutable($sourceType);
        $isTargetMutable = $this->isDateTimeMutable($targetType);

        if ($isSourceMutable === $isTargetMutable) {
            return new CopyTransformer();
        }

        // Source is mutable but target is not, transform into a datetime immutable type @TODO
        if ($isSourceMutable) {
            return new CopyTransformer();
        }

        // Target is mutable but source is not, transform into a datetime mutable type @TODO
        return new CopyTransformer();
    }

    protected function createTransformerForSource(Type $targetType, MapperMetadataInterface $mapperMetadata): ?TransformerInterface
    {
        if (Type::BUILTIN_TYPE_STRING === $targetType->getBuiltinType()) {
            return new DateTimeToStringTansformer($mapperMetadata->getDateTimeFormat());
        }

        return null;
    }

    protected function createTransformerForTarget(Type $sourceType, Type $targetType, MapperMetadataInterface $mapperMetadata): ?TransformerInterface
    {
        if (Type::BUILTIN_TYPE_STRING === $sourceType->getBuiltinType()) {
            return new StringToDateTimeTransformer($this->getClassName($targetType), $mapperMetadata->getDateTimeFormat());
        }

        return null;
    }

    private function isDateTimeType(Type $type): bool
    {
        if (Type::BUILTIN_TYPE_OBJECT !== $type->getBuiltinType()) {
            return false;
        }

        if (\DateTimeInterface::class !== $type->getClassName() && !is_subclass_of($type->getClassName(), \DateTimeInterface::class)) {
            return false;
        }

        return true;
    }

    private function getClassName(Type $type): ?string
    {
        if (\DateTimeInterface::class !== $type->getClassName()) {
            return \DateTimeImmutable::class;
        }

        return $type->getClassName();
    }

    private function isDateTimeMutable(Type $type): bool
    {
        if (Type::BUILTIN_TYPE_OBJECT !== $type->getBuiltinType()) {
            return false;
        }

        if (\DateTime::class !== $type->getClassName() && !is_subclass_of($type->getClassName(), \DateTime::class)) {
            return false;
        }

        return true;
    }
}
