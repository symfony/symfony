<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\ConversionException;
use Doctrine\DBAL\Types\Type;

/**
 * @author Teoh Han Hui <teohhanhui@gmail.com>
 */
class DoctrineFooType extends Type
{
    private const NAME = 'foo';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform): string
    {
        return $platform->getClobTypeDeclarationSQL([]);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): mixed
    {
        if (null === $value) {
            return null;
        }
        if (!$value instanceof Foo) {
            throw new ConversionException(sprintf('Expected "%s", got "%s"', 'Symfony\Bridge\Doctrine\Tests\PropertyInfo\Fixtures\Foo', get_debug_type($value)));
        }

        return $foo->bar;
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): mixed
    {
        if (null === $value) {
            return null;
        }
        if (!\is_string($value)) {
            throw ConversionException::conversionFailed($value, self::NAME);
        }

        $foo = new Foo();
        $foo->bar = $value;

        return $foo;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
