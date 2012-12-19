<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Piotr Pasich <piotr.pasich@xsolve.pl>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Form\Tests\Fixtures;

class MagicianCall
{
    private $foobar;

    public function __call($methodName, $args)
    {
        $returnValue = null;
        $property = lcfirst(substr($methodName, 3));

        switch (substr($methodName, 0, 3)) {
            case 'get':
                $returnValue = $this->get($property);
                break;
            case 'set':
                $returnValue = $this->set($property, $args);
                break;
        }

        return $returnValue;
    }

    private function set($property, $value)
    {
        $this->$property = $value;
    }

    private function get($property)
    {
        return isset($this->$property) ? $this->$property : null;
    }

}
