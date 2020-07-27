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
use Doctrine\DBAL\Types\GuidType;
use Symfony\Component\Uid\AbstractUid;

abstract class AbstractUidType extends GuidType
{
    abstract protected function getUidClass(): string;

    /**
     * {@inheritdoc}
     *
     * @throws ConversionException
     */
    public function convertToPHPValue($value, AbstractPlatform $platform): ?AbstractUid
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if ($value instanceof AbstractUid) {
            return $value;
        }

        try {
            $uuid = $this->getUidClass()::fromString($value);
        } catch (\InvalidArgumentException $e) {
            throw ConversionException::conversionFailed($value, $this->getName());
        }

        return $uuid;
    }

    /**
     * {@inheritdoc}
     *
     * @throws ConversionException
     */
    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if ($value instanceof AbstractUid) {
            return $value;
        }

        if (!\is_string($value) && !(\is_object($value) && method_exists($value, '__toString'))) {
            return null;
        }

        if ($this->getUidClass()::isValid((string) $value)) {
            return (string) $value;
        }

        throw ConversionException::conversionFailed($value, $this->getName());
    }
}
