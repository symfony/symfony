<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Tests\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\Compiler\ResolveTagsInheritancePass;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ResolveTagsInheritancePassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $container->register('grandpa', self::class)->addTag('g');
        $container->setDefinition('parent', new ChildDefinition('grandpa'))->addTag('p')->setInheritTags(true);
        $container->setDefinition('child', new ChildDefinition('parent'))->setInheritTags(true);

        (new ResolveTagsInheritancePass())->process($container);

        $expected = array('p' => array(array()), 'g' => array(array()));
        $this->assertSame($expected, $container->getDefinition('parent')->getTags());
        $this->assertSame($expected, $container->getDefinition('child')->getTags());
    }
}
