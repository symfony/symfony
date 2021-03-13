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

class ParsableDummy
{
    public $str;

    public function __construct($str)
    {
        $this->str = $str;
    }

    public static function parse($str)
    {
        return new static($str);
    }
}
