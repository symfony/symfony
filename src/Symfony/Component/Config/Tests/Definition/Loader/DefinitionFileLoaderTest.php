<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Config\Tests\Definition\Loader;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\BaseNode;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Loader\DefinitionFileLoader;
use Symfony\Component\Config\FileLocator;

class DefinitionFileLoaderTest extends TestCase
{
    public function testSupports()
    {
        $loader = new DefinitionFileLoader(new TreeBuilder('test'), new FileLocator());

        $this->assertTrue($loader->supports('foo.php'), '->supports() returns true if the resource is loadable');
        $this->assertFalse($loader->supports('foo.foo'), '->supports() returns false if the resource is not loadable');
        $this->assertTrue($loader->supports('with_wrong_ext.yml', 'php'), '->supports() returns true if the resource with forced type is loadable');
    }

    public function testLoad()
    {
        $loader = new DefinitionFileLoader($treeBuilder = new TreeBuilder('test'), new FileLocator());
        $loader->load(__DIR__.'/../../Fixtures/Loader/node_simple.php');

        $children = $treeBuilder->buildTree()->getChildren();

        $this->assertArrayHasKey('foo', $children);
        $this->assertInstanceOf(BaseNode::class, $children['foo']);
        $this->assertSame('test.foo', $children['foo']->getPath(), '->load() loads a PHP file resource');
    }
}
