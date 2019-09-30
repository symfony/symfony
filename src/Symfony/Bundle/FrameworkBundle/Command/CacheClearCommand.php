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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;
use Symfony\Component\HttpKernel\RebootableInterface;

/**
 * Clear and Warmup the cache.
 *
 * @author Francis Besset <francis.besset@gmail.com>
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final
 */
class CacheClearCommand extends Command
{
    protected static $defaultName = 'cache:clear';

    private $cacheClearer;
    private $filesystem;

    public function __construct(CacheClearerInterface $cacheClearer, Filesystem $filesystem = null)
    {
        parent::__construct();

        $this->cacheClearer = $cacheClearer;
        $this->filesystem = $filesystem ?: new Filesystem();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDefinition([
                new InputOption('no-warmup', '', InputOption::VALUE_NONE, 'Do not warm up the cache'),
                new InputOption('no-optional-warmers', '', InputOption::VALUE_NONE, 'Skip optional cache warmers (faster)'),
            ])
            ->setDescription('Clears the cache')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command clears the application cache for a given environment
and debug mode:

  <info>php %command.full_name% --env=dev</info>
  <info>php %command.full_name% --env=prod --no-debug</info>
EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $fs = $this->filesystem;
        $io = new SymfonyStyle($input, $output);

        $kernel = $this->getApplication()->getKernel();
        $realCacheDir = $kernel->getContainer()->getParameter('kernel.cache_dir');
        // the old cache dir name must not be longer than the real one to avoid exceeding
        // the maximum length of a directory or file path within it (esp. Windows MAX_PATH)
        $oldCacheDir = substr($realCacheDir, 0, -1).('~' === substr($realCacheDir, -1) ? '+' : '~');
        $fs->remove($oldCacheDir);

        if (!is_writable($realCacheDir)) {
            throw new RuntimeException(sprintf('Unable to write in the "%s" directory', $realCacheDir));
        }

        $io->comment(sprintf('Clearing the cache for the <info>%s</info> environment with debug <info>%s</info>', $kernel->getEnvironment(), var_export($kernel->isDebug(), true)));
        $this->cacheClearer->clear($realCacheDir);

        // The current event dispatcher is stale, let's not use it anymore
        $this->getApplication()->setDispatcher(new EventDispatcher());

        $containerFile = (new \ReflectionObject($kernel->getContainer()))->getFileName();
        $containerDir = basename(\dirname($containerFile));

        // the warmup cache dir name must have the same length as the real one
        // to avoid the many problems in serialized resources files
        $warmupDir = substr($realCacheDir, 0, -1).('_' === substr($realCacheDir, -1) ? '-' : '_');

        if ($output->isVerbose() && $fs->exists($warmupDir)) {
            $io->comment('Clearing outdated warmup directory...');
        }
        $fs->remove($warmupDir);

        if ($_SERVER['REQUEST_TIME'] <= filemtime($containerFile) && filemtime($containerFile) <= time()) {
            if ($output->isVerbose()) {
                $io->comment('Cache is fresh.');
            }
            if (!$input->getOption('no-warmup') && !$input->getOption('no-optional-warmers')) {
                if ($output->isVerbose()) {
                    $io->comment('Warming up optional cache...');
                }
                $warmer = $kernel->getContainer()->get('cache_warmer');
                // non optional warmers already ran during container compilation
                $warmer->enableOnlyOptionalWarmers();
                $warmer->warmUp($realCacheDir);
            }
        } else {
            $fs->mkdir($warmupDir);

            if (!$input->getOption('no-warmup')) {
                if ($output->isVerbose()) {
                    $io->comment('Warming up cache...');
                }
                $this->warmup($warmupDir, $realCacheDir, !$input->getOption('no-optional-warmers'));
            }

            if (!$fs->exists($warmupDir.'/'.$containerDir)) {
                $fs->rename($realCacheDir.'/'.$containerDir, $warmupDir.'/'.$containerDir);
                touch($warmupDir.'/'.$containerDir.'.legacy');
            }

            if ('/' === \DIRECTORY_SEPARATOR && $mounts = @file('/proc/mounts')) {
                foreach ($mounts as $mount) {
                    $mount = \array_slice(explode(' ', $mount), 1, -3);
                    if (!\in_array(array_pop($mount), ['vboxsf', 'nfs'])) {
                        continue;
                    }
                    $mount = implode(' ', $mount).'/';

                    if (0 === strpos($realCacheDir, $mount)) {
                        $io->note('For better performances, you should move the cache and log directories to a non-shared folder of the VM.');
                        $oldCacheDir = false;
                        break;
                    }
                }
            }

            if ($oldCacheDir) {
                $fs->rename($realCacheDir, $oldCacheDir);
            } else {
                $fs->remove($realCacheDir);
            }
            $fs->rename($warmupDir, $realCacheDir);

            if ($output->isVerbose()) {
                $io->comment('Removing old cache directory...');
            }

            try {
                $fs->remove($oldCacheDir);
            } catch (IOException $e) {
                if ($output->isVerbose()) {
                    $io->warning($e->getMessage());
                }
            }
        }

        if ($output->isVerbose()) {
            $io->comment('Finished');
        }

        $io->success(sprintf('Cache for the "%s" environment (debug=%s) was successfully cleared.', $kernel->getEnvironment(), var_export($kernel->isDebug(), true)));

        return 0;
    }

    private function warmup(string $warmupDir, string $realCacheDir, bool $enableOptionalWarmers = true)
    {
        // create a temporary kernel
        $kernel = $this->getApplication()->getKernel();
        if (!$kernel instanceof RebootableInterface) {
            throw new \LogicException('Calling "cache:clear" with a kernel that does not implement "Symfony\Component\HttpKernel\RebootableInterface" is not supported.');
        }
        $kernel->reboot($warmupDir);

        // warmup temporary dir
        if ($enableOptionalWarmers) {
            $warmer = $kernel->getContainer()->get('cache_warmer');
            // non optional warmers already ran during container compilation
            $warmer->enableOnlyOptionalWarmers();
            $warmer->warmUp($warmupDir);
        }

        // fix references to cached files with the real cache directory name
        $search = [$warmupDir, str_replace('\\', '\\\\', $warmupDir)];
        $replace = str_replace('\\', '/', $realCacheDir);
        foreach (Finder::create()->files()->in($warmupDir) as $file) {
            $content = str_replace($search, $replace, file_get_contents($file), $count);
            if ($count) {
                file_put_contents($file, $content);
            }
        }
    }
}
