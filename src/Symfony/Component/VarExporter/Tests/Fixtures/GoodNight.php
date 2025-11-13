<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\VarExporter\Tests\Fixtures;

class GoodNight
{
    public $good;
    protected $foo;
    private $bar;

    public function __construct()
    {
        unset($this->good);
        $this->foo = 'afternoon';
        $this->bar = 'morning';
    }

    public function __sleep(): array
    {
        $this->good = 'night';

        return ['good', 'foo', "\0*\0foo", "\0".__CLASS__."\0bar"];
    }
}
