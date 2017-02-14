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

use Symfony\Component\DependencyInjection\Compiler\AnalyzeServiceReferencesPass;
use Symfony\Component\DependencyInjection\Compiler\RepeatedPass;
use Symfony\Component\DependencyInjection\Compiler\RemoveUnusedDefinitionsPass;
use Symfony\Component\DependencyInjection\Compiler\ResolveParameterPlaceHoldersPass;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class RemoveUnusedDefinitionsPassTest extends \PHPUnit_Framework_TestCase
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
            ->setArguments(array(new Reference('bar')))
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
            ->setArguments(array(new Reference('foo')))
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
            ->setArguments(array(new Definition(null, array(new Reference('foo')))))
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
            ->setFactory(array('stdClass', 'getInstance'))
            ->setPublic(false);

        $container
            ->register('bar', 'stdClass')
            ->setFactory(array(new Reference('foo'), 'getInstance'))
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
            ->setArguments(array('%env(FOOBAR)%'))
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

    protected function process(ContainerBuilder $container)
    {
        $repeatedPass = new RepeatedPass(array(new AnalyzeServiceReferencesPass(), new RemoveUnusedDefinitionsPass()));
        $repeatedPass->process($container);
    }
}
