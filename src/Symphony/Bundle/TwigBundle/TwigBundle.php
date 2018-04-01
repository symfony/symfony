<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Bundle\TwigBundle;

use Symphony\Component\Console\Application;
use Symphony\Component\HttpKernel\Bundle\Bundle;
use Symphony\Component\DependencyInjection\Compiler\PassConfig;
use Symphony\Component\DependencyInjection\ContainerBuilder;
use Symphony\Bundle\TwigBundle\DependencyInjection\Compiler\TwigEnvironmentPass;
use Symphony\Bundle\TwigBundle\DependencyInjection\Compiler\TwigLoaderPass;
use Symphony\Bundle\TwigBundle\DependencyInjection\Compiler\ExceptionListenerPass;
use Symphony\Bundle\TwigBundle\DependencyInjection\Compiler\ExtensionPass;
use Symphony\Bundle\TwigBundle\DependencyInjection\Compiler\RuntimeLoaderPass;

/**
 * Bundle.
 *
 * @author Fabien Potencier <fabien@symphony.com>
 */
class TwigBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new ExtensionPass());
        $container->addCompilerPass(new TwigEnvironmentPass());
        $container->addCompilerPass(new TwigLoaderPass());
        $container->addCompilerPass(new ExceptionListenerPass());
        $container->addCompilerPass(new RuntimeLoaderPass(), PassConfig::TYPE_BEFORE_REMOVING);
    }

    public function registerCommands(Application $application)
    {
        // noop
    }
}
