<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Routing\Tests\Fixtures\Enum;

enum TestIntBackedEnum: int
{
    case Hearts = 10;
    case Diamonds = 20;
    case Clubs = 30;
    case Spades = 40;
}
