<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Tests\Definition\Builder;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Builder\BooleanNodeDefinition;

class BooleanNodeDefinitionTest extends TestCase
{
    /**
     * @expectedException \Symfony\Component\Config\Definition\Exception\InvalidDefinitionException
     * @expectedExceptionMessage ->cannotBeEmpty() is not applicable to BooleanNodeDefinition.
     */
    public function testCannotBeEmptyThrowsAnException()
    {
        $def = new BooleanNodeDefinition('foo');
        $def->cannotBeEmpty();
    }
}
