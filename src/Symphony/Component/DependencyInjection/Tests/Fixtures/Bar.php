<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\DependencyInjection\Tests\Fixtures;

class Bar implements BarInterface
{
    public function __construct($quz = null, \NonExistent $nonExistent = null, BarInterface $decorated = null, array $foo = array())
    {
    }

    public static function create(\NonExistent $nonExistent = null, $factory = null)
    {
    }
}
