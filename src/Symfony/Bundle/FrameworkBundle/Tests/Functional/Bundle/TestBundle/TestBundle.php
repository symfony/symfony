<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle;

use Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\DependencyInjection\AnnotationReaderPass;
use Symfony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\DependencyInjection\Config\CustomConfig;
use Symfony\Component\DependencyInjection\Compiler\CheckTypeDeclarationsPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class TestBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->setParameter('container.build_hash', 'test_bundle');
        $container->setParameter('container.build_time', time());
        $container->setParameter('container.build_id', 'test_bundle');

        /** @var $extension DependencyInjection\TestExtension */
        $extension = $container->getExtension('test');

        $extension->setCustomConfig(new CustomConfig());

        $container->addCompilerPass(new AnnotationReaderPass(), PassConfig::TYPE_AFTER_REMOVING);
        $container->addCompilerPass(new CheckTypeDeclarationsPass(true), PassConfig::TYPE_AFTER_REMOVING, -100);
    }
}
