<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;
use Symfony\Component\Uid\AbstractUid;

abstract class AbstractUidType extends Type
{
    /**
     * @return class-string<AbstractUid>
     */
    abstract protected function getUidClass(): string;

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        if ($this->hasNativeGuidType($platform)) {
            return $platform->getGuidTypeDeclarationSQL($column);
        }

        return $platform->getBinaryTypeDeclarationSQL([
            'length' => 16,
            'fixed' => true,
        ]);
    }

    /**
     * @throws ConversionException
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?AbstractUid
    {
        if ($value instanceof AbstractUid || null === $value) {
            return $value;
        }

        if (!\is_string($value)) {
            $this->throwInvalidType($value);
        }

        try {
            return $this->getUidClass()::fromString($value);
        } catch (\InvalidArgumentException $e) {
            $this->throwValueNotConvertible($value, $e);
        }
    }

    /**
     * @throws ConversionException
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        $toString = $this->hasNativeGuidType($platform) ? 'toRfc4122' : 'toBinary';

        if ($value instanceof AbstractUid) {
            return $value->$toString();
        }

        if (null === $value || '' === $value) {
            return null;
        }

        if (!\is_string($value)) {
            $this->throwInvalidType($value);
        }

        try {
            return $this->getUidClass()::fromString($value)->$toString();
        } catch (\InvalidArgumentException $e) {
            $this->throwValueNotConvertible($value, $e);
        }
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }

    private function hasNativeGuidType(AbstractPlatform $platform): bool
    {
        // Compatibility with DBAL < 3.4
        $method = method_exists($platform, 'getStringTypeDeclarationSQL')
            ? 'getStringTypeDeclarationSQL'
            : 'getVarcharTypeDeclarationSQL';

        return $platform->getGuidTypeDeclarationSQL([]) !== $platform->$method(['fixed' => true, 'length' => 36]);
    }

    private function throwInvalidType(mixed $value): never
    {
        if (!class_exists(InvalidType::class)) {
            throw ConversionException::conversionFailedInvalidType($value, $this->getName(), ['null', 'string', AbstractUid::class]);
        }

        throw InvalidType::new($value, $this->getName(), ['null', 'string', AbstractUid::class]);
    }

    private function throwValueNotConvertible(mixed $value, \Throwable $previous): never
    {
        if (!class_exists(ValueNotConvertible::class)) {
            throw ConversionException::conversionFailed($value, $this->getName(), $previous);
        }

        throw ValueNotConvertible::new($value, $this->getName(), null, $previous);
    }
}
