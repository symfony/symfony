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
use Symfony\Component\DependencyInjection\Compiler\RemoveUnusedDefinitionsPass;
use Symfony\Component\DependencyInjection\Compiler\ResolveParameterPlaceHoldersPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class RemoveUnusedDefinitionsPassTest extends TestCase
{
    public function testProcess()
    {
        $container = new ContainerBuilder();
        $container
            ->register('foo')
            ->setPublic(false)
        ;
        $container
            ->register('bar')
            ->setPublic(false)
        ;
        $container
            ->register('moo')
            ->setArguments([new Reference('bar')])
        ;

        $this->process($container);

        $this->assertFalse($container->hasDefinition('foo'));
        $this->assertTrue($container->hasDefinition('bar'));
        $this->assertTrue($container->hasDefinition('moo'));
    }

    public function testProcessRemovesUnusedDefinitionsRecursively()
    {
        $container = new ContainerBuilder();
        $container
            ->register('foo')
            ->setPublic(false)
        ;
        $container
            ->register('bar')
            ->setArguments([new Reference('foo')])
            ->setPublic(false)
        ;

        $this->process($container);

        $this->assertFalse($container->hasDefinition('foo'));
        $this->assertFalse($container->hasDefinition('bar'));
    }

    public function testProcessWorksWithInlinedDefinitions()
    {
        $container = new ContainerBuilder();
        $container
            ->register('foo')
            ->setPublic(false)
        ;
        $container
            ->register('bar')
            ->setArguments([new Definition(null, [new Reference('foo')])])
        ;

        $this->process($container);

        $this->assertTrue($container->hasDefinition('foo'));
        $this->assertTrue($container->hasDefinition('bar'));
    }

    public function testProcessWontRemovePrivateFactory()
    {
        $container = new ContainerBuilder();

        $container
            ->register('foo', 'stdClass')
            ->setFactory(['stdClass', 'getInstance'])
            ->setPublic(false);

        $container
            ->register('bar', 'stdClass')
            ->setFactory([new Reference('foo'), 'getInstance'])
            ->setPublic(false);

        $container
            ->register('foobar')
            ->addArgument(new Reference('bar'));

        $this->process($container);

        $this->assertTrue($container->hasDefinition('foo'));
        $this->assertTrue($container->hasDefinition('bar'));
        $this->assertTrue($container->hasDefinition('foobar'));
    }

    public function testProcessConsiderEnvVariablesAsUsedEvenInPrivateServices()
    {
        $container = new ContainerBuilder();
        $container->setParameter('env(FOOBAR)', 'test');
        $container
            ->register('foo')
            ->setArguments(['%env(FOOBAR)%'])
            ->setPublic(false)
        ;

        $resolvePass = new ResolveParameterPlaceHoldersPass();
        $resolvePass->process($container);

        $this->process($container);

        $this->assertFalse($container->hasDefinition('foo'));

        $envCounters = $container->getEnvCounters();
        $this->assertArrayHasKey('FOOBAR', $envCounters);
        $this->assertSame(1, $envCounters['FOOBAR']);
    }

    public function testProcessDoesNotErrorOnServicesThatDoNotHaveDefinitions()
    {
        $container = new ContainerBuilder();
        $container
            ->register('defined')
            ->addArgument(new Reference('not.defined'))
            ->setPublic(true);

        $container->set('not.defined', new \StdClass());

        $this->process($container);

        $this->assertFalse($container->hasDefinition('not.defined'));
    }

    public function testProcessWorksWithClosureErrorsInDefinitions()
    {
        $definition = new Definition();
        $definition->addError(function () {
            return 'foo bar';
        });

        $container = new ContainerBuilder();
        $container
            ->setDefinition('foo', $definition)
            ->setPublic(false)
        ;

        $this->process($container);

        $this->assertFalse($container->hasDefinition('foo'));
    }

    protected function process(ContainerBuilder $container)
    {
        (new RemoveUnusedDefinitionsPass())->process($container);
    }
}
