<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Fixtures\Dynamic;

enum PizzaSize: int
{
    case Small = 12;
    case Medium = 14;
    case Large = 16;

    public function getReadable(): string
    {
        return match ($this) {
            self::Small => '12 inch',
            self::Medium => '14 inch',
            self::Large => '16 inch',
        };
    }
}
