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

use Symfony\Component\DependencyInjection\Compiler\ReplaceAliasByActualDefinitionPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class ReplaceAliasByActualDefinitionPassTest extends \PHPUnit_Framework_TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();

        $container->register('a', '\stdClass');

        $bDefinition = new Definition('\stdClass');
        $bDefinition->setPublic(false);
        $container->setDefinition('b', $bDefinition);

        $container->setAlias('a_alias', 'a');
        $container->setAlias('b_alias', 'b');

        $this->process($container);

        $this->assertTrue($container->has('a'), '->process() does nothing to public definitions.');
        $this->assertTrue($container->hasAlias('a_alias'));
        $this->assertFalse($container->has('b'), '->process() removes non-public definitions.');
        $this->assertTrue(
            $container->has('b_alias') && !$container->hasAlias('b_alias'),
            '->process() replaces alias to actual.'
        );
    }

    protected function process(ContainerBuilder $container)
    {
        $pass = new ReplaceAliasByActualDefinitionPass();
        $pass->process($container);
    }
}
