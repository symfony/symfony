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
use Symfony\Component\DependencyInjection\Compiler\ReplaceAliasByActualDefinitionPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

require_once __DIR__.'/../Fixtures/includes/foo.php';

class ReplaceAliasByActualDefinitionPassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();

        $aDefinition = $container->register('a', '\stdClass');
        $aDefinition->setFactory([new Reference('b'), 'createA']);

        $bDefinition = new Definition('\stdClass');
        $bDefinition->setPublic(false);
        $container->setDefinition('b', $bDefinition);

        $container->setAlias('a_alias', 'a');
        $container->setAlias('b_alias', 'b');

        $container->setAlias('container', 'service_container');

        $this->process($container);

        $this->assertTrue($container->has('a'), '->process() does nothing to public definitions.');
        $this->assertTrue($container->hasAlias('a_alias'));
        $this->assertFalse($container->has('b'), '->process() removes non-public definitions.');
        $this->assertTrue(
            $container->has('b_alias') && !$container->hasAlias('b_alias'),
            '->process() replaces alias to actual.'
        );

        $this->assertTrue($container->has('container'));

        $resolvedFactory = $aDefinition->getFactory();
        $this->assertSame('b_alias', (string) $resolvedFactory[0]);
    }

    public function testProcessWithInvalidAlias()
    {
        $this->expectException('InvalidArgumentException');
        $container = new ContainerBuilder();
        $container->setAlias('a_alias', 'a');
        $this->process($container);
    }

    protected function process(ContainerBuilder $container)
    {
        $pass = new ReplaceAliasByActualDefinitionPass();
        $pass->process($container);
    }
}
