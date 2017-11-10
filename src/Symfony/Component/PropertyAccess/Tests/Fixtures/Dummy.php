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

use Symfony\Component\PropertyAccess\Annotation\PropertyAccessor;
use Symfony\Component\PropertyAccess\Annotation\GetterAccessor;

/**
 * Fixtures for testing metadata.
 */
class Dummy extends DummyParent
{
    /**
     * @PropertyAccessor(getter="getter1", setter="setter1", adder="adder1", remover="remover1")
     */
    protected $foo;

    /**
     * @PropertyAccessor(getter="getter2")
     */
    protected $bar;

    /**
     * @return mixed
     */
    public function getter1()
    {
        return $this->foo;
    }

    /**
     * @param mixed $foo
     */
    public function setter1($foo)
    {
        $this->foo = $foo;
    }

    /**
     * @return mixed
     */
    public function getter2()
    {
        return $this->bar;
    }

    /**
     * @param mixed $bar
     */
    public function setBar($bar)
    {
        $this->bar = $bar;
    }

    /**
     * @GetterAccessor(property="test")
     */
    public function testChild()
    {
        return 'child';
    }
}
