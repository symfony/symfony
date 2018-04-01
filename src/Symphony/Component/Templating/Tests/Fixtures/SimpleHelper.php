<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Templating\Tests\Fixtures;

use Symphony\Component\Templating\Helper\Helper;

class SimpleHelper extends Helper
{
    protected $value = '';

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function __toString()
    {
        return $this->value;
    }

    public function getName()
    {
        return 'foo';
    }
}
