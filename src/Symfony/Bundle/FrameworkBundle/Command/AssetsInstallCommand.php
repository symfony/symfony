<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
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
 * Command that places bundle web assets into a given directory.
 *
 * @author Fabien Potencier <fabien@symfony.com>
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
                new InputArgument('target', InputArgument::REQUIRED, 'The target directory (usually "web")'),
            ))
            ->addOption('symlink', null, InputOption::VALUE_NONE, 'Symlinks the assets instead of copying it')
            ->setHelp(<<<EOT
The <info>assets:install</info> command installs bundle assets into a given
directory (e.g. the web directory).

<info>./app/console assets:install web [--symlink]</info>

A "bundles" directory will be created inside the target directory, and the
"Resources/public" directory of each bundle will be copied into it.

To create a symlink to each bundle instead of copying its assets, use the
<info>--symlink</info> option.

EOT
            )
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
        $filesystem->mkdir($input->getArgument('target').'/bundles/', 0777);

        foreach ($this->container->get('kernel')->getBundles() as $bundle) {
            if (is_dir($originDir = $bundle->getPath().'/Resources/public')) {
                $targetDir = $input->getArgument('target').'/bundles/'.preg_replace('/bundle$/', '', strtolower($bundle->getName()));

                $output->writeln(sprintf('Installing assets for <comment>%s</comment> into <comment>%s</comment>', $bundle->getNamespace(), $targetDir));

                $filesystem->remove($targetDir);

                if ($input->getOption('symlink')) {
                    $filesystem->symlink($originDir, $targetDir);
                } else {
                    $filesystem->mkdir($targetDir, 0777);
                    $filesystem->mirror($originDir, $targetDir);
                }
            }
        }
    }
}
