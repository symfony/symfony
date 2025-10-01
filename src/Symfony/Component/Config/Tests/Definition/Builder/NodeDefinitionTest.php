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
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;

class NodeDefinitionTest extends TestCase
{
    public function testSetPathSeparatorChangesChildren()
    {
        $parentNode = new ArrayNodeDefinition('name');
        $childNode = $this->createMock(NodeDefinition::class);

        $childNode
            ->expects($this->once())
            ->method('setPathSeparator')
            ->with('/');
        $childNode
            ->expects($this->once())
            ->method('setParent')
            ->with($parentNode)
            ->willReturn($childNode);
        $parentNode->append($childNode);

        $parentNode->setPathSeparator('/');
    }

    public function testDocUrl()
    {
        $node = new ArrayNodeDefinition('node');
        $node->docUrl('https://example.com/doc/{package}/{version:major}.{version:minor}', 'phpunit/phpunit');

        $r = new \ReflectionObject($node);
        $p = $r->getProperty('attributes');

        $this->assertMatchesRegularExpression('~^https://example.com/doc/phpunit/phpunit/\d+\.\d+$~', $p->getValue($node)['docUrl']);
    }

    public function testDocUrlWithoutPackage()
    {
        $node = new ArrayNodeDefinition('node');
        $node->docUrl('https://example.com/doc/empty{version:major}.empty{version:minor}');

        $r = new \ReflectionObject($node);
        $p = $r->getProperty('attributes');

        $this->assertSame('https://example.com/doc/empty.empty', $p->getValue($node)['docUrl']);
    }

    public function testUnknownPackageThrowsException()
    {
        $this->expectException(\OutOfBoundsException::class);
        $this->expectExceptionMessage('Package "phpunit/invalid" is not installed');

        $node = new ArrayNodeDefinition('node');
        $node->docUrl('https://example.com/doc/{package}/{version:major}.{version:minor}', 'phpunit/invalid');
    }
}
