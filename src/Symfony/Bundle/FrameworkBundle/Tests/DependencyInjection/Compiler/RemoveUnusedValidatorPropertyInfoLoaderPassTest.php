<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection\Compiler;

use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler\RemoveUnusedValidatorPropertyInfoLoaderPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Validator\Mapping\Loader\PropertyInfoLoader;

class RemoveUnusedValidatorPropertyInfoLoaderPassTest extends TestCase
{
    public function testTheLoaderIsKeptWhenThePropertyInfoExtractorIsAvailable()
    {
        $container = $this->createContainer();
        $container->register('property_info', PropertyInfoExtractor::class);

        new RemoveUnusedValidatorPropertyInfoLoaderPass()->process($container);

        $this->assertTrue($container->hasDefinition('validator.property_info_loader'));
    }

    public function testTheLoaderIsRemovedWhenThePropertyInfoExtractorIsMissing()
    {
        $container = $this->createContainer();

        new RemoveUnusedValidatorPropertyInfoLoaderPass()->process($container);

        $this->assertFalse($container->hasDefinition('validator.property_info_loader'));
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register('validator.property_info_loader', PropertyInfoLoader::class);

        return $container;
    }
}
