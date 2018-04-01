<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Config\Tests\Fixtures\Builder;

use Symphony\Component\Config\Definition\Builder\NodeDefinition;
use Symphony\Component\Config\Tests\Fixtures\BarNode;

class BarNodeDefinition extends NodeDefinition
{
    protected function createNode()
    {
        return new BarNode($this->name);
    }
}
