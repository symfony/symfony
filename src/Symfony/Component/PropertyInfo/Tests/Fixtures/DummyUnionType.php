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
 * @author Baptiste Leduc <baptiste.leduc@gmail.com>
 */
class DummyUnionType
{
    /**
     * @var string|int
     */
    public $a;

    /**
     * @var (string|int)[]
     */
    public $b;

    /**
     * @var array<string|int>
     */
    public $c;

    /**
     * @var array<string|int, array<string>>
     */
    public $d;

    /**
     * @var (Dummy<array<mixed, string>, (int | (string<DefaultValue>)[])> | ParentDummy | null)
     */
    public $e;
}
