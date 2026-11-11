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
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\PropertyInfo\DependencyInjection\PhpStanExtractorResolverPass;
use Symfony\Component\PropertyInfo\Extractor\PhpStanExtractor;
use Symfony\Component\TypeInfo\TypeResolver\StringTypeResolver;

class PhpStanExtractorResolverPassTest extends TestCase
{
    public function testTheConfiguredResolverIsKept()
    {
        $container = $this->createContainer();
        $container->register('type_info.resolver.string', StringTypeResolver::class);

        new PhpStanExtractorResolverPass()->process($container);

        $this->assertEquals(new Reference('type_info.resolver.string'), $container->getDefinition('property_info.phpstan_extractor')->getArgument(4));
    }

    public function testTheExtractorGetsAResolverWithoutRefinementsWhenNoneIsRegistered()
    {
        $container = $this->createContainer();

        new PhpStanExtractorResolverPass()->process($container);

        $this->assertEquals(new Definition(StringTypeResolver::class, [null, null, [], false]), $container->getDefinition('property_info.phpstan_extractor')->getArgument(4));
    }

    public function testNothingIsDoneWithoutTheExtractor()
    {
        $container = new ContainerBuilder();

        new PhpStanExtractorResolverPass()->process($container);

        $this->assertFalse($container->hasDefinition('property_info.phpstan_extractor'));
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register('property_info.phpstan_extractor', PhpStanExtractor::class)
            ->setArguments([null, null, null, true, new Reference('type_info.resolver.string')]);

        return $container;
    }
}
