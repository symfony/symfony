<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Asset\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Process\Process;

/**
 * Save git commit hash to parameter and then use it as assets version.
 *
 * @author Evgenii Sokolov <ewgraf@gmail.com>
 */
class GitCommitHashCompilerPass implements CompilerPassInterface
{
    /**
     * @param ContainerBuilder $container
     * @throws \Exception
     */
    public function process(ContainerBuilder $container)
    {
        $process = new Process('git log -1');

        if (0 !== $process->run()) {
            throw new \Exception("Git command return wrong exit code");
        }

        $container->setParameter('git_commit_hash_version_strategy', sha1($process->getOutput()));
    }
}
