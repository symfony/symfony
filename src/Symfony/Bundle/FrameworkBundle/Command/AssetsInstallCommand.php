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

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * Command that places bundle web assets into a given directory.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 * @author Gábor Egyed <gabor.egyed@gmail.com>
 *
 * @final
 */
#[AsCommand(name: 'assets:install', description: 'Install bundle\'s web assets under a public directory')]
class AssetsInstallCommand extends Command
{
    public const METHOD_COPY = 'copy';
    public const METHOD_ABSOLUTE_SYMLINK = 'absolute symlink';
    public const METHOD_RELATIVE_SYMLINK = 'relative symlink';

    public function __construct(
        private Filesystem $filesystem,
        private string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDefinition([
                new InputArgument('target', InputArgument::OPTIONAL, 'The target directory', null),
            ])
            ->addOption('symlink', null, InputOption::VALUE_NONE, 'Symlink the assets instead of copying them')
            ->addOption('relative', null, InputOption::VALUE_NONE, 'Make relative symlinks')
            ->addOption('no-cleanup', null, InputOption::VALUE_NONE, 'Do not remove the assets of the bundles that no longer exist')
            ->setHelp(<<<'EOT'
The <info>%command.name%</info> command installs bundle assets into a given
directory (e.g. the <comment>public</comment> directory).

  <info>php %command.full_name% public</info>

A "bundles" directory will be created inside the target directory and the
"Resources/public" directory of each bundle will be copied into it.

To create a symlink to each bundle instead of copying its assets, use the
<info>--symlink</info> option (will fall back to hard copies when symbolic links aren't possible:

  <info>php %command.full_name% public --symlink</info>

To make symlink relative, add the <info>--relative</info> option:

  <info>php %command.full_name% public --symlink --relative</info>

EOT
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var KernelInterface $kernel */
        $kernel = $this->getApplication()->getKernel();
        $targetArg = rtrim($input->getArgument('target') ?? '', '/');
        if (!$targetArg) {
            $targetArg = $this->getPublicDirectory($kernel->getContainer());
        }

        if (!is_dir($targetArg)) {
            $targetArg = $kernel->getProjectDir().'/'.$targetArg;

            if (!is_dir($targetArg)) {
                throw new InvalidArgumentException(\sprintf('The target directory "%s" does not exist.', $targetArg));
            }
        }

        $bundlesDir = $targetArg.'/bundles/';

        $io = new SymfonyStyle($input, $output);
        $io->newLine();

        if ($input->getOption('relative')) {
            $expectedMethod = self::METHOD_RELATIVE_SYMLINK;
            $io->text('Trying to install assets as <info>relative symbolic links</info>.');
        } elseif ($input->getOption('symlink')) {
            $expectedMethod = self::METHOD_ABSOLUTE_SYMLINK;
            $io->text('Trying to install assets as <info>absolute symbolic links</info>.');
        } else {
            $expectedMethod = self::METHOD_COPY;
            $io->text('Installing assets as <info>hard copies</info>.');
        }

        $io->newLine();

        $rows = [];
        $copyUsed = false;
        $exitCode = 0;
        $validAssetDirs = [];
        /** @var BundleInterface $bundle */
        foreach ($kernel->getBundles() as $bundle) {
            if (!is_dir($originDir = $bundle->getPath().'/Resources/public') && !is_dir($originDir = $bundle->getPath().'/public')) {
                continue;
            }

            $assetDir = preg_replace('/bundle$/', '', strtolower($bundle->getName()));
            $targetDir = $bundlesDir.$assetDir;
            $validAssetDirs[] = $assetDir;

            if (OutputInterface::VERBOSITY_VERBOSE <= $output->getVerbosity()) {
                $message = \sprintf("%s\n-> %s", $bundle->getName(), $targetDir);
            } else {
                $message = $bundle->getName();
            }

            try {
                $this->filesystem->remove($targetDir);

                if (self::METHOD_RELATIVE_SYMLINK === $expectedMethod) {
                    $method = $this->relativeSymlinkWithFallback($originDir, $targetDir);
                } elseif (self::METHOD_ABSOLUTE_SYMLINK === $expectedMethod) {
                    $method = $this->absoluteSymlinkWithFallback($originDir, $targetDir);
                } else {
                    $method = $this->hardCopy($originDir, $targetDir);
                }

                if (self::METHOD_COPY === $method) {
                    $copyUsed = true;
                }

                if ($method === $expectedMethod) {
                    $rows[] = [\sprintf('<fg=green;options=bold>%s</>', '\\' === \DIRECTORY_SEPARATOR ? 'OK' : "\xE2\x9C\x94" /* HEAVY CHECK MARK (U+2714) */), $message, $method];
                } else {
                    $rows[] = [\sprintf('<fg=yellow;options=bold>%s</>', '\\' === \DIRECTORY_SEPARATOR ? 'WARNING' : '!'), $message, $method];
                }
            } catch (\Exception $e) {
                $exitCode = 1;
                $rows[] = [\sprintf('<fg=red;options=bold>%s</>', '\\' === \DIRECTORY_SEPARATOR ? 'ERROR' : "\xE2\x9C\x98" /* HEAVY BALLOT X (U+2718) */), $message, $e->getMessage()];
            }
        }
        // remove the assets of the bundles that no longer exist
        if (!$input->getOption('no-cleanup') && is_dir($bundlesDir)) {
            $dirsToRemove = Finder::create()->depth(0)->directories()->exclude($validAssetDirs)->in($bundlesDir);
            $this->filesystem->remove($dirsToRemove);
        }

        if ($rows) {
            $io->table(['', 'Bundle', 'Method / Error'], $rows);
        }

        if (0 !== $exitCode) {
            $io->error('Some errors occurred while installing assets.');
        } else {
            if ($copyUsed) {
                $io->note('Some assets were installed via copy. If you make changes to these assets you have to run this command again.');
            }
            $io->success($rows ? 'All assets were successfully installed.' : 'No assets were provided by any bundle.');
        }

        return $exitCode;
    }

    /**
     * Try to create relative symlink.
     *
     * Falling back to absolute symlink and finally hard copy.
     */
    private function relativeSymlinkWithFallback(string $originDir, string $targetDir): string
    {
        try {
            $this->symlink($originDir, $targetDir, true);
            $method = self::METHOD_RELATIVE_SYMLINK;
        } catch (IOException) {
            $method = $this->absoluteSymlinkWithFallback($originDir, $targetDir);
        }

        return $method;
    }

    /**
     * Try to create absolute symlink.
     *
     * Falling back to hard copy.
     */
    private function absoluteSymlinkWithFallback(string $originDir, string $targetDir): string
    {
        try {
            $this->symlink($originDir, $targetDir);
            $method = self::METHOD_ABSOLUTE_SYMLINK;
        } catch (IOException) {
            // fall back to copy
            $method = $this->hardCopy($originDir, $targetDir);
        }

        return $method;
    }

    /**
     * Creates symbolic link.
     *
     * @throws IOException if link cannot be created
     */
    private function symlink(string $originDir, string $targetDir, bool $relative = false): void
    {
        if ($relative) {
            $this->filesystem->mkdir(\dirname($targetDir));
            $originDir = $this->filesystem->makePathRelative($originDir, realpath(\dirname($targetDir)));
        }
        $this->filesystem->symlink($originDir, $targetDir);
        if (!file_exists($targetDir)) {
            throw new IOException(\sprintf('Symbolic link "%s" was created but appears to be broken.', $targetDir), 0, null, $targetDir);
        }
    }

    /**
     * Copies origin to target.
     */
    private function hardCopy(string $originDir, string $targetDir): string
    {
        $this->filesystem->mkdir($targetDir, 0777);
        // We use a custom iterator to ignore VCS files
        $this->filesystem->mirror($originDir, $targetDir, Finder::create()->ignoreDotFiles(false)->in($originDir));

        return self::METHOD_COPY;
    }

    private function getPublicDirectory(ContainerInterface $container): string
    {
        $defaultPublicDir = 'public';

        if (null === $this->projectDir && !$container->hasParameter('kernel.project_dir')) {
            return $defaultPublicDir;
        }

        $composerFilePath = ($this->projectDir ?? $container->getParameter('kernel.project_dir')).'/composer.json';

        if (!file_exists($composerFilePath)) {
            return $defaultPublicDir;
        }

        $composerConfig = json_decode($this->filesystem->readFile($composerFilePath), true, flags: \JSON_THROW_ON_ERROR);

        return $composerConfig['extra']['public-dir'] ?? $defaultPublicDir;
    }
}
