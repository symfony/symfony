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

        $aDefinition = $container->register('a', '\stdClass')->setPublic(true);
        $aDefinition->setFactory([new Reference('b'), 'createA']);

        $bDefinition = new Definition('\stdClass');
        $container->setDefinition('b', $bDefinition);

        $container->setAlias('a_alias', 'a')->setPublic(true)->setDeprecated('foo/bar', '1.2', '%alias_id%');
        $container->setAlias('b_alias', 'b')->setPublic(true)->setDeprecated('foo/bar', '1.2', '%alias_id%');

        $container->setAlias('container', 'service_container');

        $this->process($container);

        $this->assertTrue($container->has('a'), '->process() does nothing to public definitions.');
        $this->assertTrue($container->hasAlias('a_alias'));
        $this->assertTrue($container->getAlias('a_alias')->isDeprecated());
        $this->assertFalse($container->has('b'), '->process() removes non-public definitions.');
        $this->assertTrue(
            $container->has('b_alias') && !$container->hasAlias('b_alias'),
            '->process() replaces alias to actual.'
        );
        $this->assertTrue($container->getDefinition('b_alias')->hasTag('container.private'));

        $this->assertTrue($container->has('container'));

        $resolvedFactory = $aDefinition->getFactory();
        $this->assertSame('b_alias', (string) $resolvedFactory[0]);
    }

    public function testProcessWithInvalidAlias()
    {
        $this->expectException(\InvalidArgumentException::class);
        $container = new ContainerBuilder();
        $container->setAlias('a_alias', 'a');
        $this->process($container);
    }

    public function testProcessDoesNotInvertAliasChainWithDeprecatedAlias()
    {
        $container = new ContainerBuilder();

        // Original service (will be decorated)
        $container->register('my_service', \stdClass::class);

        // Private decorator that decorates the service
        $container->register('my_decorator', \stdClass::class)
            ->setDecoratedService('my_service')
            ->setPublic(false);

        // After DecoratorServicePass runs, we'll have:
        // - my_service -> my_decorator (alias created by decorator)
        // - my_decorator (the decorator definition, private)
        // - my_decorator.inner (the original service)
        //
        // Simulate this state:
        $container->removeDefinition('my_service');
        $container->setAlias('my_service', 'my_decorator')->setPublic(true);

        // Deprecated alias pointing to the original service ID
        // After ResolveReferencesToAliasesPass, this would point to my_decorator
        $container->setAlias('MyServiceInterface', 'my_decorator')
            ->setPublic(true)
            ->setDeprecated('my/package', '1.0', 'The "%alias_id%" alias is deprecated.');

        // Now both aliases point to the same private definition:
        // - my_service -> my_decorator (not deprecated)
        // - MyServiceInterface -> my_decorator (deprecated)

        $this->process($container);

        // The non-deprecated alias (my_service) should be used for renaming,
        // NOT the deprecated one (MyServiceInterface)
        $this->assertTrue($container->hasDefinition('my_service'), 'my_service should be the definition, not an alias');
        $this->assertFalse($container->hasAlias('my_service'), 'my_service should not be an alias');

        // The deprecated alias should remain an alias pointing to my_service
        $this->assertTrue($container->hasAlias('MyServiceInterface'), 'MyServiceInterface should be an alias');
        $this->assertSame('my_service', (string) $container->getAlias('MyServiceInterface'));
        $this->assertTrue($container->getAlias('MyServiceInterface')->isDeprecated());
    }

    protected function process(ContainerBuilder $container)
    {
        $pass = new ReplaceAliasByActualDefinitionPass();
        $pass->process($container);
    }
}
