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

class TestClassMagicGet
{
    private $magicProperty;

    public $publicProperty;

    public function __construct($value)
    {
        $this->magicProperty = $value;
    }

    public function __set($property, $value)
    {
        if ('magicProperty' === $property) {
            $this->magicProperty = $value;
        }
    }

    public function __get($property)
    {
        if ('magicProperty' === $property) {
            return $this->magicProperty;
        }

        if ('constantMagicProperty' === $property) {
            return 'constant value';
        }
    }
}
