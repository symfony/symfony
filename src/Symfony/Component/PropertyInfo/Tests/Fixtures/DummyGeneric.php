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

interface IFace {}

class Clazz {}

class DummyGeneric
{

    /**
     * @var Clazz<Dummy>
     */
    public $basicClass;

    /**
     * @var ?Clazz<Dummy>
     */
    public $nullableClass;

    /**
     * @var IFace<Dummy>
     */
    public $basicInterface;

    /**
     * @var ?IFace<Dummy>
     */
    public $nullableInterface;

}
