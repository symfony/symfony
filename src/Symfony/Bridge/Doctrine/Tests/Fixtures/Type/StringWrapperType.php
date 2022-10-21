<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\Doctrine\Tests\Fixtures\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\StringType;

class StringWrapperType extends StringType
{
    public function convertToDatabaseValue($value, AbstractPlatform $platform): mixed
    {
        return $value instanceof StringWrapper ? $value->getString() : null;
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): mixed
    {
        return new StringWrapper($value);
    }

    public function getName(): string
    {
        return 'string_wrapper';
    }
}
