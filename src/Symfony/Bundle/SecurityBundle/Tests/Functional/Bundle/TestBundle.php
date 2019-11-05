<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional\Bundle;

use Symfony\Component\DependencyInjection\Compiler\CheckTypeDeclarationsPass;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class TestBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        $container->setParameter('container.build_hash', 'test_bundle');
        $container->setParameter('container.build_time', time());
        $container->setParameter('container.build_id', 'test_bundle');

        $container->addCompilerPass(new CheckTypeDeclarationsPass(true), PassConfig::TYPE_AFTER_REMOVING, -100);
    }
}
