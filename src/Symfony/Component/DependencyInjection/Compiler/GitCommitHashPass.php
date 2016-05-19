<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Process\Process;

/**
 * Save git commit hash to parameter and then use it as assets version.
 *
 * @author Evgenii Sokolov <ewgraf@gmail.com>
 */
class GitCommitHashPass implements CompilerPassInterface
{
    /**
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->hasDefinition('assets.git_version_strategy')) {
            return;
        }

        $process = new Process('git rev-parse HEAD', $container->getParameter('kernel.root_dir'));
        $process->mustRun();
        $container->getDefinition('assets.git_version_strategy')->replaceArgument(0, trim($process->getOutput()));
    }
}
