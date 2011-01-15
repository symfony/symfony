<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien.potencier@symfony-project.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\Output;

/**
 * AssetsInstallCommand.
 *
 * @author Fabien Potencier <fabien.potencier@symfony-project.com>
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
            ->addOption('symlink', null, InputOption::VALUE_NONE, 'Symlinks the assets instead of copying it')
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

        $filesystem = $this->container->get('filesystem');

        // Create the bundles directory otherwise symlink will fail.
        $filesystem->mkdirs($input->getArgument('target').'/bundles/', 0777);

        foreach ($this->container->get('kernel')->getBundles() as $bundle) {
            if (is_dir($originDir = $bundle->getPath().'/Resources/public')) {
                $output->writeln(sprintf('Installing assets for <comment>%s</comment>', $bundle->getNamespace()));

                $targetDir = $input->getArgument('target').'/bundles/'.preg_replace('/bundle$/', '', strtolower($bundle->getName()));

                $filesystem->remove($targetDir);

                if ($input->getOption('symlink')) {
                    $filesystem->symlink($originDir, $targetDir);
                } else {
                    $filesystem->mkdirs($targetDir, 0777);
                    $filesystem->mirror($originDir, $targetDir);
                }
            }
        }
    }
}
