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

use Symfony\Component\Console\Helper\Table;
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
 * @author Gábor Egyed <gabor.egyed@gmail.com>
 */
class AssetsInstallCommand extends ContainerAwareCommand
{
    /**
     * @var \Symfony\Component\Filesystem\Filesystem
     */
    private $filesystem;

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
            ->setHelp(<<<EOT
The <info>%command.name%</info> command installs bundle assets into a given
directory (e.g. the web directory).

<info>php %command.full_name% web</info>

A "bundles" directory will be created inside the target directory, and the
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

        $this->filesystem = $this->getContainer()->get('filesystem');

        // Create the bundles directory otherwise symlink will fail.
        $bundlesDir = $targetArg.'/bundles/';
        $this->filesystem->mkdir($bundlesDir, 0777);

        // relative implies symlink
        $symlink = $input->getOption('symlink') || $input->getOption('relative');

        if ($symlink) {
            $output->writeln('Trying to install assets as <comment>symbolic links</comment>.');
        } else {
            $output->writeln('Installing assets as <comment>hard copies</comment>.');
        }

        $table = new Table($output);
        $table->setHeaders(array('Source', 'Target', 'Method / Error'));

        $failed = 0;
        foreach ($this->getContainer()->get('kernel')->getBundles() as $bundle) {
            if (!is_dir($originDir = $bundle->getPath().'/Resources/public')) {
                continue;
            }

            $targetDir = $bundlesDir.preg_replace('/bundle$/', '', strtolower($bundle->getName()));
            $this->filesystem->remove($targetDir);

            if ($symlink) {
                try {
                    $relative = $this->symlink($originDir, $targetDir, $input->getOption('relative'));
                    $table->addRow(array(
                        $bundle->getNamespace(),
                        $targetDir,
                        sprintf('%s symbolic link', $relative ? 'relative' : 'absolute'),
                    ));

                    continue;
                } catch (IOException $e) {
                    // fall back to hard copy
                }
            }

            try {
                $this->hardCopy($originDir, $targetDir);
                $table->addRow(array($bundle->getNamespace(), $targetDir, 'hard copy'));
            } catch (IOException $e) {
                $table->addRow(array($bundle->getNamespace(), $targetDir, sprintf('<error>%s</error>', $e->getMessage())));
                $failed = 1;
            }
        }

        $table->render();

        return $failed;
    }

    /**
     * Creates links with absolute as a fallback.
     *
     * @param string $origin
     * @param string $target
     * @param bool $relative
     *
     * @throws IOException If link can not be created.
     *
     * @return bool Created a relative link or not.
     */
    private function symlink($origin, $target, $relative = true)
    {
        try {
            $this->filesystem->symlink(
                $relative ? $this->filesystem->makePathRelative($origin, realpath(dirname($target))) : $origin,
                $target
            );

            if (!file_exists($target)) {
                throw new IOException(sprintf('Symbolic link "%s" is created but appears to be broken.', $target), 0, null, $target);
            }
        } catch (IOException $e) {
            if ($relative) {
                // try again with absolute
                return $this->symlink($origin, $target, false);
            }

            throw $e;
        }

        return $relative;
    }

    /**
     * @param string $originDir
     * @param string $targetDir
     */
    private function hardCopy($originDir, $targetDir)
    {
        $this->filesystem->mkdir($targetDir, 0777);
        // We use a custom iterator to ignore VCS files
        $this->filesystem->mirror($originDir, $targetDir, Finder::create()->ignoreDotFiles(false)->in($originDir));
    }
}
