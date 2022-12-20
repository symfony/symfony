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
use Symfony\Component\Config\Definition\Exception\InvalidDefinitionException;

class BooleanNodeDefinitionTest extends TestCase
{
    public function testCannotBeEmptyThrowsAnException()
    {
        self::expectException(InvalidDefinitionException::class);
        self::expectExceptionMessage('->cannotBeEmpty() is not applicable to BooleanNodeDefinition.');
        $def = new BooleanNodeDefinition('foo');
        $def->cannotBeEmpty();
    }

    public function testSetDeprecated()
    {
        $def = new BooleanNodeDefinition('foo');
        $def->setDeprecated('vendor/package', '1.1', 'The "%path%" node is deprecated.');

        $node = $def->getNode();

        self::assertTrue($node->isDeprecated());
        $deprecation = $node->getDeprecation($node->getName(), $node->getPath());
        self::assertSame('The "foo" node is deprecated.', $deprecation['message']);
        self::assertSame('vendor/package', $deprecation['package']);
        self::assertSame('1.1', $deprecation['version']);
    }
}
