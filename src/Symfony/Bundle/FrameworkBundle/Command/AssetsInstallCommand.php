<?php

namespace Symfony\Bundle\FrameworkBundle\Command;

use Symfony\Components\Console\Input\InputArgument;
use Symfony\Components\Console\Input\InputOption;
use Symfony\Components\Console\Input\InputInterface;
use Symfony\Components\Console\Output\OutputInterface;
use Symfony\Components\Console\Output\Output;
use Symfony\Bundle\FrameworkBundle\Util\Filesystem;

/*
 * This file is part of the Symfony framework.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

/**
 * AssetsInstallCommand.
 *
 * @author     Fabien Potencier <fabien.potencier@symfony-project.com>
 */
class AssetsInstallCommand extends Command
{
    /**
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputArgument('target', InputArgument::REQUIRED, 'The target directory'),
            ))
            ->setName('assets:install')
        ;
    }

    /**
     * @see Command
     *
     * @throws \InvalidArgumentException When the target directory does not exist
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!is_dir($input->getArgument('target'))) {
            throw new \InvalidArgumentException(sprintf('The target directory "%s" does not exist.', $input->getArgument('target')));
        }

        $filesystem = new Filesystem();

        foreach ($this->container->getKernelService()->getBundles() as $bundle) {
            if (is_dir($originDir = $bundle->getPath().'/Resources/public')) {
                $output->writeln(sprintf('Installing assets for <comment>%s\\%s</comment>', $bundle->getNamespacePrefix(), $bundle->getName()));

                $targetDir = $input->getArgument('target').'/bundles/'.preg_replace('/bundle$/', '', strtolower($bundle->getName()));

                $filesystem->remove($targetDir);
                mkdir($targetDir, 0777, true);
                $filesystem->mirror($originDir, $targetDir);
            }
        }
    }
}
