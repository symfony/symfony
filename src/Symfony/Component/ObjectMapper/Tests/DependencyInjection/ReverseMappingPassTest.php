<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\ObjectMapper\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\ObjectMapper\DependencyInjection\ReverseMappingPass;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap\Cost;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap\CostRequestView;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap\Quote;
use Symfony\Component\ObjectMapper\Tests\Fixtures\ClassMap\QuoteRequestView;

final class ReverseMappingPassTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        if (!class_exists(ContainerBuilder::class)) {
            self::markTestSkipped('The DependencyInjection component is not available.');
        }
    }

    public function testProcessWithoutReverseClassDefinition()
    {
        $this->expectNotToPerformAssertions();

        (new ReverseMappingPass())->process(new ContainerBuilder());
    }

    public function testProcessCollectsTaggedResources()
    {
        $container = new ContainerBuilder();
        $factory = $this->registerReverseClassFactory($container);

        $this->registerMappedResource($container, QuoteRequestView::class, Quote::class);
        $this->registerMappedResource($container, CostRequestView::class, Cost::class);

        (new ReverseMappingPass())->process($container);

        $this->assertSame([
            Quote::class => [QuoteRequestView::class],
            Cost::class => [CostRequestView::class],
        ], $factory->getArgument(1));
    }

    public function testProcessCollectsMultipleTargetsPerSourceOnce()
    {
        $container = new ContainerBuilder();
        $factory = $this->registerReverseClassFactory($container);

        $this->registerMappedResource($container, QuoteRequestView::class, Quote::class);
        $this->registerMappedResource($container, CostRequestView::class, Quote::class);
        $this->registerMappedResource($container, QuoteRequestView::class, Quote::class, 'duplicate');

        (new ReverseMappingPass())->process($container);

        $this->assertSame([Quote::class => [QuoteRequestView::class, CostRequestView::class]], $factory->getArgument(1));
    }

    public function testProcessSkipsTagsWithoutSourceOrTarget()
    {
        $container = new ContainerBuilder();
        $factory = $this->registerReverseClassFactory($container);

        $definition = new Definition(QuoteRequestView::class);
        $definition->addResourceTag('object_mapper.map', ['source' => Quote::class]);
        $container->setDefinition(QuoteRequestView::class, $definition);

        (new ReverseMappingPass())->process($container);

        $this->assertSame([], $factory->getArgument(1));
    }

    public function testProcessAcceptsAbstractResources()
    {
        $container = new ContainerBuilder();
        $factory = $this->registerReverseClassFactory($container);

        $this->registerMappedResource($container, QuoteRequestView::class, Quote::class)->setAbstract(true);

        (new ReverseMappingPass())->process($container);

        $this->assertSame([Quote::class => [QuoteRequestView::class]], $factory->getArgument(1));
    }

    private function registerReverseClassFactory(ContainerBuilder $container): Definition
    {
        $definition = new Definition();
        $definition->setArguments([null, []]);
        $container->setDefinition('object_mapper.metadata_factory.reverse_class', $definition);

        return $definition;
    }

    private function registerMappedResource(ContainerBuilder $container, string $targetClass, string $sourceClass, string $idSuffix = ''): Definition
    {
        $definition = new Definition($targetClass);
        // as added by the #[Map] attribute autoconfiguration registered in FrameworkExtension
        $definition->addResourceTag('object_mapper.map', [
            'source' => $sourceClass,
            'target' => $targetClass,
        ]);
        $container->setDefinition($targetClass.$idSuffix, $definition);

        return $definition;
    }
}
