<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Templating\Tests\Fixtures;

use Symfony\Component\Templating\Helper\Helper;

class SimpleHelper extends Helper
{
    protected $value = '';

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function getName(): string
    {
        return 'foo';
    }
}
