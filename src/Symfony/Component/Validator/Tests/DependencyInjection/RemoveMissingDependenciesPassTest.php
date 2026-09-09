<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Validator\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Validator\DependencyInjection\RemoveMissingDependenciesPass;
use Symfony\Component\Validator\Mapping\Loader\PropertyInfoLoader;

class RemoveMissingDependenciesPassTest extends TestCase
{
    public function testThePropertyInfoLoaderIsKeptWhenTheExtractorIsAvailable()
    {
        $container = $this->createContainer();
        $container->register('property_info', PropertyInfoExtractor::class);

        new RemoveMissingDependenciesPass()->process($container);

        $this->assertTrue($container->hasDefinition('validator.property_info_loader'));
    }

    public function testThePropertyInfoLoaderIsRemovedWhenTheExtractorIsMissing()
    {
        $container = $this->createContainer();

        new RemoveMissingDependenciesPass()->process($container);

        $this->assertFalse($container->hasDefinition('validator.property_info_loader'));
    }

    public function testTheConfiguredTranslationDomainWinsOverTheDefault()
    {
        $container = $this->createContainer();
        $container->setParameter('validator.translation_domain', 'validators');
        $container->setParameter('.validator.translation_domain', 'messages');

        new RemoveMissingDependenciesPass()->process($container);

        $this->assertSame('messages', $container->getParameter('validator.translation_domain'));
        $this->assertFalse($container->hasParameter('.validator.translation_domain'));
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register('validator.property_info_loader', PropertyInfoLoader::class);

        return $container;
    }
}
