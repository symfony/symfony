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
    private const TYPE_A = 'a';
    private const TYPE_B = 'b';

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

    /**
     * @var self::TYPE_*|null
     */
    public $f;

    /**
     * @var non-empty-array<string|int>
     */
    public $g;
}
