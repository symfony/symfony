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
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Finder\Finder;

/**
 * Command that places bundle web assets into a given directory.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class AssetsInstallCommand extends ContainerAwareCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('assets:install')
            ->setDefinition(array(
                new InputArgument('target', InputArgument::OPTIONAL, 'The target directory', 'web'),
            ))
            ->addOption('symlink', null, InputOption::VALUE_NONE, 'Symlinks the assets instead of copying it')
            ->addOption('relative', null, InputOption::VALUE_NONE, 'Make relative symlinks')
            ->setDescription('Installs bundles web assets under a public web directory')
            ->setHelp(<<<'EOT'
The <info>%command.name%</info> command installs bundle assets into a given
directory (e.g. the <comment>web</comment> directory).

  <info>php %command.full_name% web</info>

A "bundles" directory will be created inside the target directory and the
"Resources/public" directory of each bundle will be copied into it.

To create a symlink to each bundle instead of copying its assets, use the
<info>--symlink</info> option (will fall back to hard copies when symbolic links aren't possible:

  <info>php %command.full_name% web --symlink</info>

To make symlink relative, add the <info>--relative</info> option:

  <info>php %command.full_name% web --symlink --relative</info>

EOT
            )
        ;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \InvalidArgumentException When the target directory does not exist or symlink cannot be used
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $targetArg = rtrim($input->getArgument('target'), '/');

        if (!is_dir($targetArg)) {
            throw new \InvalidArgumentException(sprintf('The target directory "%s" does not exist.', $input->getArgument('target')));
        }

        $filesystem = $this->getContainer()->get('filesystem');

        // Create the bundles directory otherwise symlink will fail.
        $bundlesDir = $targetArg.'/bundles/';
        $filesystem->mkdir($bundlesDir, 0777);

        // relative implies symlink
        $symlink = $input->getOption('symlink') || $input->getOption('relative');

        if ($symlink) {
            $output->writeln('Trying to install assets as <comment>symbolic links</comment>.');
        } else {
            $output->writeln('Installing assets as <comment>hard copies</comment>.');
        }

        foreach ($this->getContainer()->get('kernel')->getBundles() as $bundle) {
            if (is_dir($originDir = $bundle->getPath().'/Resources/public')) {
                $targetDir = $bundlesDir.preg_replace('/bundle$/', '', strtolower($bundle->getName()));

                $output->writeln(sprintf('Installing assets for <comment>%s</comment> into <comment>%s</comment>', $bundle->getNamespace(), $targetDir));

                $filesystem->remove($targetDir);

                if ($symlink) {
                    if ($input->getOption('relative')) {
                        $relativeOriginDir = $filesystem->makePathRelative($originDir, realpath($bundlesDir));
                    } else {
                        $relativeOriginDir = $originDir;
                    }

                    try {
                        $filesystem->symlink($relativeOriginDir, $targetDir);
                        if (!file_exists($targetDir)) {
                            throw new IOException('Symbolic link is broken');
                        }
                        $output->writeln('The assets were installed using symbolic links.');
                    } catch (IOException $e) {
                        if (!$input->getOption('relative')) {
                            $this->hardCopy($originDir, $targetDir);
                            $output->writeln('It looks like your system doesn\'t support symbolic links, so the assets were installed by copying them.');
                        }

                        // try again without the relative option
                        try {
                            $filesystem->symlink($originDir, $targetDir);
                            if (!file_exists($targetDir)) {
                                throw new IOException('Symbolic link is broken');
                            }
                            $output->writeln('It looks like your system doesn\'t support relative symbolic links, so the assets were installed by using absolute symbolic links.');
                        } catch (IOException $e) {
                            $this->hardCopy($originDir, $targetDir);
                            $output->writeln('It looks like your system doesn\'t support symbolic links, so the assets were installed by copying them.');
                        }
                    }
                } else {
                    $this->hardCopy($originDir, $targetDir);
                }
            }
        }
    }

    /**
     * @param string $originDir
     * @param string $targetDir
     */
    private function hardCopy($originDir, $targetDir)
    {
        $filesystem = $this->getContainer()->get('filesystem');

        $filesystem->mkdir($targetDir, 0777);
        // We use a custom iterator to ignore VCS files
        $filesystem->mirror($originDir, $targetDir, Finder::create()->ignoreDotFiles(false)->in($originDir));
    }
}
