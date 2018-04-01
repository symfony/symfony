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

class Ticket5775Object
{
    private $property;

    public function getProperty()
    {
        return $this->property;
    }

    private function setProperty()
    {
    }

    public function __set($property, $value)
    {
        $this->$property = $value;
    }
}
