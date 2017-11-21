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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;
use Symfony\Component\HttpKernel\RebootableInterface;
use Symfony\Component\Finder\Finder;

/**
 * Clear and Warmup the cache.
 *
 * @author Francis Besset <francis.besset@gmail.com>
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @final since version 3.4
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
            ->setDefinition(array(
                new InputOption('no-warmup', '', InputOption::VALUE_NONE, 'Do not warm up the cache'),
                new InputOption('no-optional-warmers', '', InputOption::VALUE_NONE, 'Skip optional cache warmers (faster)'),
            ))
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
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $kernel = $this->getApplication()->getKernel();
        $realCacheDir = $kernel->getContainer()->getParameter('kernel.cache_dir');
        // the old cache dir name must not be longer than the real one to avoid exceeding
        // the maximum length of a directory or file path within it (esp. Windows MAX_PATH)
        $oldCacheDir = substr($realCacheDir, 0, -1).('~' === substr($realCacheDir, -1) ? '+' : '~');

        if (!is_writable($realCacheDir)) {
            throw new \RuntimeException(sprintf('Unable to write in the "%s" directory', $realCacheDir));
        }

        if ($this->filesystem->exists($oldCacheDir)) {
            $this->filesystem->remove($oldCacheDir);
        }

        $io->comment(sprintf('Clearing the cache for the <info>%s</info> environment with debug <info>%s</info>', $kernel->getEnvironment(), var_export($kernel->isDebug(), true)));
        $this->cacheClearer->clear($realCacheDir);

        // The current event dispatcher is stale, let's not use it anymore
        $this->getApplication()->setDispatcher(new EventDispatcher());

        if ($input->getOption('no-warmup')) {
            $this->filesystem->rename($realCacheDir, $oldCacheDir);
        } else {
            $this->warmupCache($input, $output, $realCacheDir, $oldCacheDir);
        }

        if ($output->isVerbose()) {
            $io->comment('Removing old cache directory...');
        }

        try {
            $this->filesystem->remove($oldCacheDir);
        } catch (IOException $e) {
            $io->warning($e->getMessage());
        }

        if ($output->isVerbose()) {
            $io->comment('Finished');
        }

        $io->success(sprintf('Cache for the "%s" environment (debug=%s) was successfully cleared.', $kernel->getEnvironment(), var_export($kernel->isDebug(), true)));
    }

    private function warmupCache(InputInterface $input, OutputInterface $output, string $realCacheDir, string $oldCacheDir)
    {
        $io = new SymfonyStyle($input, $output);

        // the warmup cache dir name must have the same length than the real one
        // to avoid the many problems in serialized resources files
        $realCacheDir = realpath($realCacheDir);
        $warmupDir = substr($realCacheDir, 0, -1).('_' === substr($realCacheDir, -1) ? '-' : '_');

        if ($this->filesystem->exists($warmupDir)) {
            if ($output->isVerbose()) {
                $io->comment('Clearing outdated warmup directory...');
            }
            $this->filesystem->remove($warmupDir);
        }

        if ($output->isVerbose()) {
            $io->comment('Warming up cache...');
        }
        $this->warmup($warmupDir, $realCacheDir, !$input->getOption('no-optional-warmers'));

        $this->filesystem->rename($realCacheDir, $oldCacheDir);
        if ('\\' === DIRECTORY_SEPARATOR) {
            sleep(1);  // workaround for Windows PHP rename bug
        }
        $this->filesystem->rename($warmupDir, $realCacheDir);
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
        $warmer = $kernel->getContainer()->get('cache_warmer');
        if ($enableOptionalWarmers) {
            $warmer->enableOptionalWarmers();
        }
        $warmer->warmUp($warmupDir);

        // fix references to cached files with the real cache directory name
        $search = array($warmupDir, str_replace('\\', '\\\\', $warmupDir));
        $replace = str_replace('\\', '/', $realCacheDir);
        foreach (Finder::create()->files()->in($warmupDir) as $file) {
            $content = str_replace($search, $replace, file_get_contents($file), $count);
            if ($count) {
                file_put_contents($file, $content);
            }
        }
    }
}
