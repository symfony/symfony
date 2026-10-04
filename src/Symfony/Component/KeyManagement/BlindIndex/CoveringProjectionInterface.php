<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\KeyManagement\BlindIndex;

/**
 * A projection covering several forms of one value: every prefix of a name, every bucket a number
 * falls in.
 *
 * An index over it has a tag per form rather than one, which
 * {@see \Symfony\Component\KeyManagement\BlindIndexInterface::allOf()} returns, and a column that
 * holds them all. It is a type of its own so that such an index is refused where one tag is
 * expected, whatever the value at hand.
 *
 * @author Florent Morselli <florent.morselli@spomky-labs.com>
 *
 * @experimental
 */
interface CoveringProjectionInterface
{
    /**
     * @return list<string> the forms of the value this projection covers
     */
    public function cover(#[\SensitiveParameter] string $value): array;
}
