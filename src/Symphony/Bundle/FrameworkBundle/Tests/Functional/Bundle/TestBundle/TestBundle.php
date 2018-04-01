<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle;

use Symphony\Component\HttpKernel\Bundle\Bundle;
use Symphony\Component\DependencyInjection\Compiler\PassConfig;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\DependencyInjection\AnnotationReaderPass;
use Symphony\Bundle\FrameworkBundle\Tests\Functional\Bundle\TestBundle\DependencyInjection\Config\CustomConfig;

class TestBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        /** @var $extension DependencyInjection\TestExtension */
        $extension = $container->getExtension('test');

        $extension->setCustomConfig(new CustomConfig());

        $container->addCompilerPass(new AnnotationReaderPass(), PassConfig::TYPE_AFTER_REMOVING);
    }
}
