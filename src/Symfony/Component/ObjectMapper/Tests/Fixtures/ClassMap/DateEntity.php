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

#[Map(target: DateView::class)]
final class DateEntity
{
    #[Map(transform: [self::class, 'dateToString'])]
    public ?\DateTimeImmutable $date = null;

    public static function dateToString(?\DateTimeImmutable $date): ?string
    {
        return $date?->format('Y-m-d');
    }
}
