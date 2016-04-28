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
        $process = new Process('git log -1', $container->getParameter('kernel.root_dir'));
        $process->mustRun();
        $container->setParameter('git_commit_hash', sha1($process->getOutput()));
    }
}
