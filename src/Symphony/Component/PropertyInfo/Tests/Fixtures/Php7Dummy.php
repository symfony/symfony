<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\PropertyInfo\Tests\Fixtures;

/**
 * @author Kévin Dunglas <dunglas@gmail.com>
 */
class Php7Dummy
{
    public function getFoo(): array
    {
    }

    public function setBar(int $bar)
    {
    }

    public function addBaz(string $baz)
    {
    }
}
