<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Fixtures;

class Magician
{
    private $foobar;

    public function __set($property, $value)
    {
        $this->$property = $value;
    }

    public function __get($property)
    {
        return isset($this->$property) ? $this->$property : null;
    }

    public function __call($method, $arguments) {
        if (preg_match('/^(get|is|has)([A-Z].*)$/', $method, $matches)) {
            return $this->{lcfirst($matches[2])};
        }
        return null;
    }
}
