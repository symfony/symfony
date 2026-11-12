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

class IntRangeDummy
{
    /**
     * @var int<0, 100>
     */
    public $a;

    /**
     * @var int<min, 100>|null
     */
    public $b;

    /**
     * @var int<50, max>
     */
    public $c;

    /**
     * @var int<0, 5, 9>
     */
    public $malformed;

    /**
     * @var list<int<5, 1>>
     */
    public $invertedInList;

    /**
     * @var int<0, self::MISSING>
     */
    public $missingBound;
}
