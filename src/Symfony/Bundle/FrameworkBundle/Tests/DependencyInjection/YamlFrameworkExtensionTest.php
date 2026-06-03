<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\DependencyInjection;

use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class YamlFrameworkExtensionTest extends FrameworkExtensionTestCase
{
    protected function loadFromFile(ContainerBuilder $container, $file)
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/Fixtures/yml'));
        $loader->load($file.'.yml');
    }

    public function testWorkflowWithSimplisticPlaceFollowedByComplexPlaceWithAlternativeSyntax()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Unrecognized option "wait_for_journalist" under "framework.workflows.workflows.article.places.1". Available options are "metadata", "name".');
        $this->createContainerFromFile('workflow_with_simplistic_place_follow_by_complex_place_config_with_alternative_syntax');
    }

    public function testWorkflowWithComplexPlaceFollowedBySimplisticPlaceWithAlternativeSyntax()
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Unrecognized option "draft" under "framework.workflows.workflows.article.places.0". Available options are "metadata", "name".');
        $this->createContainerFromFile('workflow_with_complex_place_follow_by_simplistic_place_config_with_alternative_syntax');
    }
}
