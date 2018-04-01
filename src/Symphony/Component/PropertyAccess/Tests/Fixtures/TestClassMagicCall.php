<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\PropertyAccess\Tests\Fixtures;

class TestClassMagicCall
{
    private $magicCallProperty;

    public function __construct($value)
    {
        $this->magicCallProperty = $value;
    }

    public function __call($method, array $args)
    {
        if ('getMagicCallProperty' === $method) {
            return $this->magicCallProperty;
        }

        if ('getConstantMagicCallProperty' === $method) {
            return 'constant value';
        }

        if ('setMagicCallProperty' === $method) {
            $this->magicCallProperty = reset($args);
        }
    }
}
