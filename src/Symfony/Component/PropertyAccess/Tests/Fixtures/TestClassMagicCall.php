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

        return null;
    }
}
