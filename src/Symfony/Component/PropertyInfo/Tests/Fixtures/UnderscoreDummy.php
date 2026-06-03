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

/**
 * Fixture class for testing underscore-only properties.
 */
class UnderscoreDummy
{
    private float $_;
    private float $__;

    public function get_(): float
    {
        return $this->_;
    }

    public function get__(): float
    {
        return $this->__;
    }
}
