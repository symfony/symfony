<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Serializer\Tests\Fixtures;

/**
 * @author Jérôme Desjardin <jewome62@gmail.com>
 */
class DeepObjectPopulateChildDummy
{
    public $foo;

    public $bar;

    // needed to have GetSetNormalizer consider this class as supported
    public function getFoo()
    {
        return $this->foo;
    }

    public function setFoo($foo)
    {
        $this->foo = $foo;
    }

    public function setBar($bar)
    {
        $this->bar = $bar;
    }
}
