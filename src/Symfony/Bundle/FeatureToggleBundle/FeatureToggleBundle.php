<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FeatureToggleBundle;

use Symfony\Bundle\FeatureToggleBundle\DependencyInjection\CompilerPass\DebugPass;
use Symfony\Bundle\FeatureToggleBundle\DependencyInjection\CompilerPass\FeatureCollectionPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class FeatureToggleBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new FeatureCollectionPass());
        $container->addCompilerPass(new DebugPass());
    }
}
