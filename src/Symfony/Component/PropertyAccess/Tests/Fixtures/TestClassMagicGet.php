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

class TestClassMagicGet
{
    private $magicProperty;

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
