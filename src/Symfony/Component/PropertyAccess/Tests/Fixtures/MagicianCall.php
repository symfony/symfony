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

class MagicianCall
{
    private $foobar;

    public function __call($name, $args)
    {
        $property = lcfirst(substr($name, 3));
        if ('get' === substr($name, 0, 3)) {

            return isset($this->$property) ? $this->$property : null;
        } elseif ('set' === substr($name, 0, 3)) {
            $value = 1 == count($args) ? $args[0] : null;
            $this->$property = $value;
        }
    }
}
