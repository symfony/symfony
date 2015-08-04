<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyAccess\Tests\Fixtures;

class TestClassIsWritable
{
    protected $value;

    public function getValue()
    {
        return $this->value;
    }

    public function __construct($value)
    {
        $this->value = $value;
    }
}