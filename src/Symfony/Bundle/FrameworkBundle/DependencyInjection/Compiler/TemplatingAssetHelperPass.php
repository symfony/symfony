<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

@trigger_error('The '.__NAMESPACE__.'\TemplatingAssetHelperPass class is deprecated since Symfony 2.7 and will be removed in 3.0.', E_USER_DEPRECATED);

/**
 * @deprecated since 2.7, will be removed in 3.0
 */
class TemplatingAssetHelperPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('templating.helper.assets')) {
            return;
        }

        $assetsHelperDefinition = $container->getDefinition('templating.helper.assets');
        $args = $assetsHelperDefinition->getArguments();

        if ('request' === $this->getPackageScope($container, $args[0])) {
            $assetsHelperDefinition->setScope('request');

            return;
        }

        if (!array_key_exists(1, $args)) {
            return;
        }

        if (!is_array($args[1])) {
            return;
        }

        foreach ($args[1] as $arg) {
            if ('request' === $this->getPackageScope($container, $arg)) {
                $assetsHelperDefinition->setScope('request');

                break;
            }
        }
    }

    private function getPackageScope(ContainerBuilder $container, $package)
    {
        if ($package instanceof Reference) {
            return $container->findDefinition((string) $package)->getScope();
        }

        if ($package instanceof Definition) {
            return $package->getScope();
        }

        // Someone did some voodoo with a compiler pass. So we ignore this
        // 'package'. Can we be sure, it's a package anyway?
    }
}
