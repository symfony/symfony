<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\TwigBundle;

use Symfony\Bundle\TwigBundle\DependencyInjection\Compiler\ExtensionPass;
use Symfony\Bundle\TwigBundle\DependencyInjection\Compiler\RuntimeLoaderPass;
use Symfony\Bundle\TwigBundle\DependencyInjection\Compiler\TwigEnvironmentPass;
use Symfony\Bundle\TwigBundle\DependencyInjection\Compiler\TwigLoaderPass;
use Symfony\Component\Console\Application;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Bundle.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class TwigBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        // ExtensionPass must be run before the FragmentRendererPass as it adds tags that are processed later
        $container->addCompilerPass(new ExtensionPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 10);
        $container->addCompilerPass(new TwigEnvironmentPass());
        $container->addCompilerPass(new TwigLoaderPass());
        $container->addCompilerPass(new RuntimeLoaderPass(), PassConfig::TYPE_BEFORE_REMOVING);
    }

    public function registerCommands(Application $application)
    {
        // noop
    }
}
