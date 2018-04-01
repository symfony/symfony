<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Serializer\Tests\Fixtures;

class AbstractDummyFirstChild extends AbstractDummy
{
    public $bar;

    public function __construct($foo = null, $bar = null)
    {
        parent::__construct($foo);

        $this->bar = $bar;
    }
}
