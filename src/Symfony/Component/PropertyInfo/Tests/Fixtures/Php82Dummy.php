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

class Php82Dummy
{
    public null $nil = null;

    public false $false = false;

    public true $true = true;

    public (\Traversable&\Countable)|null $someCollection = null;
}
