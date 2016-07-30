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
use Symfony\Component\DependencyInjection\Reference;

require_once __DIR__.'/../Fixtures/includes/foo.php';

class ReplaceAliasByActualDefinitionPassTest extends \PHPUnit_Framework_TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();

        $aDefinition = $container->register('a', '\stdClass');
        $aDefinition->setFactoryService('b', false);

        $aDefinition->setFactory(array(new Reference('b'), 'createA'));

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

        $this->assertSame('b_alias', $aDefinition->getFactoryService(false));
        $this->assertTrue($container->has('container'));

        $resolvedFactory = $aDefinition->getFactory();
        $this->assertSame('b_alias', (string) $resolvedFactory[0]);
    }

    /**
     * @group legacy
     */
    public function testPrivateAliasesInFactory()
    {
        $container = new ContainerBuilder();

        $container->register('a', 'Bar\FooClass');
        $container->register('b', 'Bar\FooClass')
            ->setFactoryService('a')
            ->setFactoryMethod('getInstance');

        $container->register('c', 'stdClass')->setPublic(false);
        $container->setAlias('c_alias', 'c');

        $this->process($container);

        $this->assertInstanceOf('Bar\FooClass', $container->get('b'));
    }

    /**
     * @expectedException \InvalidArgumentException
     */
    public function testProcessWithInvalidAlias()
    {
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
