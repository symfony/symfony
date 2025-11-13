<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Tests\Fixtures\FakeVendor;

abstract class Base
{
    public $baseFoo;

    protected $baseBar;

    public function baseBaz()
    {
    }

    public function baseQux()
    {
    }
}
