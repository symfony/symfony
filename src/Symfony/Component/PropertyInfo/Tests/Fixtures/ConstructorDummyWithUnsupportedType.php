<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\Fixtures;

class ConstructorDummyWithUnsupportedType
{
    /**
     * @param int-mask-of<1|2|4> $flags
     */
    public function __construct(int $flags)
    {
    }
}
