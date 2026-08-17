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

    public function testProcessAcceptsAbstractResources()
    {
        $container = new ContainerBuilder();

        $factory = new Definition();
        $factory->setArguments([null, []]);
        $container->setDefinition('object_mapper.metadata_factory.reverse_class', $factory);

        $definition = new Definition(QuoteRequestView::class);
        $definition->setAbstract(true);
        $definition->addResourceTag('object_mapper.map', [
            'source' => Quote::class,
            'target' => QuoteRequestView::class,
        ]);
        $container->setDefinition(QuoteRequestView::class, $definition);

        (new ReverseMappingPass())->process($container);

        $this->assertSame([Quote::class => [QuoteRequestView::class]], $factory->getArgument(1));
    }
}
