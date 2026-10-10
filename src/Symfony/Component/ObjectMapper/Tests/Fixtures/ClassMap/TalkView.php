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

#[Map(source: Talk::class)]
final class TalkView
{
    public string $title;

    #[Map(transform: [self::class, 'toList'])]
    public array $speakers;

    #[Map(if: false)]
    public ?string $notes = null;

    public static function toList(iterable $speakers): array
    {
        return iterator_to_array($speakers, false);
    }
}
