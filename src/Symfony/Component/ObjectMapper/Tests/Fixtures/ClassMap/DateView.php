<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap;

use Symfony\Component\ObjectMapper\Attribute\Map;

#[Map(target: DateEntity::class)]
final class DateView
{
    #[Map(transform: [self::class, 'stringToDate'])]
    public ?string $date = null;

    public static function stringToDate(?string $date): ?\DateTimeImmutable
    {
        return null === $date ? null : new \DateTimeImmutable($date);
    }
}
