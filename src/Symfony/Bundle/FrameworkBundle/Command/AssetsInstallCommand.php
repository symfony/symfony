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

use Exception;
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

            try {
                $this->filesystem->remove($targetDir);

                if ($input->getOption('relative')) {
                    $methodOrError = $this->relativeSymlinkWithFallback($originDir, $targetDir);
                } elseif ($symlink) {
                    $methodOrError = $this->absoluteSymlinkWithFallback($originDir, $targetDir);
                } else {
                    $methodOrError = $this->hardCopy($originDir, $targetDir);
                }
            } catch (Exception $e) {
                $methodOrError = sprintf('<error>%s</error>', $e->getMessage());
                $failed = 1;
            }

            $table->addRow(array($bundle->getNamespace(), $targetDir, $methodOrError));
        }

        $table->render();

        return $failed;
    }

    /**
     * Try to create relative symlink.
     *
     * Falling back to absolute symlink and finally hard copy.
     *
     * @param string $originDir
     * @param string $targetDir
     *
     * @return string
     */
    private function relativeSymlinkWithFallback($originDir, $targetDir)
    {
        try {
            $this->symlink($originDir, $targetDir, true);
            $method = 'relative symlink';
        } catch (IOException $e) {
            $method = $this->absoluteSymlinkWithFallback($originDir, $targetDir);
        }

        return $method;
    }

    /**
     * Try to create absolute symlink.
     *
     * Falling back to hard copy.
     *
     * @param string $originDir
     * @param string $targetDir
     *
     * @return string
     */
    private function absoluteSymlinkWithFallback($originDir, $targetDir)
    {
        try {
            $this->symlink($originDir, $targetDir);
            $method = 'absolute symlink';
        } catch (IOException $e) {
            // fall back to copy
            $method = $this->hardCopy($originDir, $targetDir);
        }

        return $method;
    }

    /**
     * Creates symbolic link.
     *
     * @param string $originDir
     * @param string $targetDir
     * @param bool $relative
     *
     * @throws IOException If link can not be created.
     */
    private function symlink($originDir, $targetDir, $relative = false)
    {
        if ($relative) {
            $originDir = $this->filesystem->makePathRelative($originDir, realpath(dirname($targetDir)));
        }
        $this->filesystem->symlink($originDir, $targetDir);
        if (!file_exists($targetDir)) {
            throw new IOException(sprintf('Symbolic link "%s" is created but appears to be broken.', $targetDir), 0, null, $targetDir);
        }
    }

    /**
     * Copies origin to target.
     *
     * @param string $originDir
     * @param string $targetDir
     *
     * @return string
     */
    private function hardCopy($originDir, $targetDir)
    {
        $this->filesystem->mkdir($targetDir, 0777);
        // We use a custom iterator to ignore VCS files
        $this->filesystem->mirror($originDir, $targetDir, Finder::create()->ignoreDotFiles(false)->in($originDir));

        return 'copy';
    }
}
