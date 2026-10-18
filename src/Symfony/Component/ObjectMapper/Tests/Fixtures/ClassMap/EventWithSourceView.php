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

final class EventWithSourceView
{
    #[Map(source: 'date', transform: [self::class, 'toDate'])]
    public ?string $date = null;

    public static function toDate(?string $date): ?\DateTimeImmutable
    {
        return null === $date ? null : new \DateTimeImmutable($date);
    }
}
