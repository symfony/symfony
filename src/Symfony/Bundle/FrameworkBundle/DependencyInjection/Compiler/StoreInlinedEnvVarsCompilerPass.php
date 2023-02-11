<?php

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Stores environment variables used during build, for debugging purposes
 *
 * @author Valtteri Rauhala <valtzu@gmail.com>
 */
class StoreInlinedEnvVarsCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $buildTimeEnvVars = [];
        foreach ($container->getEnvCounters() as $env => $count) {
            if ($count > 0) {
                $buildTimeEnvVars[$env] = $container->resolveEnvPlaceholders("%env($env)%", true);
            }
        }

        $container->setParameter('.debug.container.build_time_env_vars', $buildTimeEnvVars);
    }
}
