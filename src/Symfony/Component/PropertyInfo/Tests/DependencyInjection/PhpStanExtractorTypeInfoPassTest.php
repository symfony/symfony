<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\PropertyInfo\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\PropertyInfo\DependencyInjection\PhpStanExtractorTypeInfoPass;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\TypeInfo\TypeContext\TypeContextFactory;
use Symfony\Component\TypeInfo\TypeResolver\StringTypeResolver;

class PhpStanExtractorTypeInfoPassTest extends TestCase
{
    public function testTheTypeInfoServicesAreInjected()
    {
        $container = $this->createContainer([['set'], '$allowPrivateAccess' => false]);

        new PhpStanExtractorTypeInfoPass()->process($container);

        $this->assertEquals(
            [['set'], '$allowPrivateAccess' => false, '$stringTypeResolver' => new Reference('type_info.resolver.string'), '$typeContextFactory' => new Reference('type_info.type_context_factory')],
            $container->getDefinition('property_info.phpstan_extractor')->getArguments(),
        );
    }

    public function testTheTypeContextFactoryIsNotInjectedWithoutTheStringTypeResolver()
    {
        $container = new ContainerBuilder();
        $container->register('property_info.phpstan_extractor', PhpStanExtractor::class);
        $container->register('type_info.type_context_factory', TypeContextFactory::class);

        new PhpStanExtractorTypeInfoPass()->process($container);

        $this->assertSame([], $container->getDefinition('property_info.phpstan_extractor')->getArguments());
    }

    public function testNothingHappensWithoutThePhpStanExtractor()
    {
        $container = new ContainerBuilder();
        $container->register('type_info.resolver.string', StringTypeResolver::class);
        $container->register('type_info.type_context_factory', TypeContextFactory::class);

        new PhpStanExtractorTypeInfoPass()->process($container);

        $this->assertFalse($container->hasDefinition('property_info.phpstan_extractor'));
    }

    private function createContainer(array $extractorArguments): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register('property_info.phpstan_extractor', PhpStanExtractor::class)->setArguments($extractorArguments);
        $container->register('type_info.resolver.string', StringTypeResolver::class);
        $container->register('type_info.type_context_factory', TypeContextFactory::class);

        return $container;
    }
}
