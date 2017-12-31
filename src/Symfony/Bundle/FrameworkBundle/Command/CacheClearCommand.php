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

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\CacheClearer\CacheClearerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
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
class CacheClearCommand extends ContainerAwareCommand
{
    protected static $defaultName = 'cache:clear';

    private $cacheClearer;
    private $filesystem;
    private $warning;

    /**
     * @param CacheClearerInterface $cacheClearer
     * @param Filesystem|null       $filesystem
     */
    public function __construct($cacheClearer = null, Filesystem $filesystem = null)
    {
        if (!$cacheClearer instanceof CacheClearerInterface) {
            @trigger_error(sprintf('%s() expects an instance of "%s" as first argument since Symfony 3.4. Not passing it is deprecated and will throw a TypeError in 4.0.', __METHOD__, CacheClearerInterface::class), E_USER_DEPRECATED);

            parent::__construct($cacheClearer);

            return;
        }

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
        // BC to be removed in 4.0
        if (null === $this->cacheClearer) {
            $this->cacheClearer = $this->getContainer()->get('cache_clearer');
            $this->filesystem = $this->getContainer()->get('filesystem');
            $realCacheDir = $this->getContainer()->getParameter('kernel.cache_dir');
        }

        $fs = $this->filesystem;
        $io = new SymfonyStyle($input, $output);

        $kernel = $this->getApplication()->getKernel();
        $realCacheDir = isset($realCacheDir) ? $realCacheDir : $kernel->getContainer()->getParameter('kernel.cache_dir');
        // the old cache dir name must not be longer than the real one to avoid exceeding
        // the maximum length of a directory or file path within it (esp. Windows MAX_PATH)
        $oldCacheDir = substr($realCacheDir, 0, -1).('~' === substr($realCacheDir, -1) ? '+' : '~');
        $fs->remove($oldCacheDir);

        if (!is_writable($realCacheDir)) {
            throw new \RuntimeException(sprintf('Unable to write in the "%s" directory', $realCacheDir));
        }

        $io->comment(sprintf('Clearing the cache for the <info>%s</info> environment with debug <info>%s</info>', $kernel->getEnvironment(), var_export($kernel->isDebug(), true)));
        $this->cacheClearer->clear($realCacheDir);

        // The current event dispatcher is stale, let's not use it anymore
        $this->getApplication()->setDispatcher(new EventDispatcher());

        $containerDir = new \ReflectionObject($kernel->getContainer());
        $containerDir = basename(dirname($containerDir->getFileName()));

        // the warmup cache dir name must have the same length as the real one
        // to avoid the many problems in serialized resources files
        $warmupDir = substr($realCacheDir, 0, -1).('_' === substr($realCacheDir, -1) ? '-' : '_');

        if ($output->isVerbose() && $fs->exists($warmupDir)) {
            $io->comment('Clearing outdated warmup directory...');
        }
        $fs->remove($warmupDir);
        $fs->mkdir($warmupDir);

        if (!$input->getOption('no-warmup')) {
            if ($output->isVerbose()) {
                $io->comment('Warming up cache...');
            }
            $this->warmup($warmupDir, $realCacheDir, !$input->getOption('no-optional-warmers'));

            if ($this->warning) {
                @trigger_error($this->warning, E_USER_DEPRECATED);
                $io->warning($this->warning);
                $this->warning = null;
            }
        }

        $containerDir = $fs->exists($warmupDir.'/'.$containerDir) ? false : $containerDir;

        $fs->rename($realCacheDir, $oldCacheDir);
        $fs->rename($warmupDir, $realCacheDir);

        if ($containerDir) {
            $fs->rename($oldCacheDir.'/'.$containerDir, $realCacheDir.'/'.$containerDir);
            touch($realCacheDir.'/'.$containerDir.'.legacy');
        }

        if ($output->isVerbose()) {
            $io->comment('Removing old cache directory...');
        }

        try {
            $fs->remove($oldCacheDir);
        } catch (IOException $e) {
            $io->warning($e->getMessage());
        }

        if ($output->isVerbose()) {
            $io->comment('Finished');
        }

        $io->success(sprintf('Cache for the "%s" environment (debug=%s) was successfully cleared.', $kernel->getEnvironment(), var_export($kernel->isDebug(), true)));
    }

    /**
     * @param string $warmupDir
     * @param string $realCacheDir
     * @param bool   $enableOptionalWarmers
     */
    protected function warmup($warmupDir, $realCacheDir, $enableOptionalWarmers = true)
    {
        // create a temporary kernel
        $realKernel = $this->getApplication()->getKernel();
        if ($realKernel instanceof RebootableInterface) {
            $realKernel->reboot($warmupDir);
            $tempKernel = $realKernel;
        } else {
            $this->warning = 'Calling "cache:clear" with a kernel that does not implement "Symfony\Component\HttpKernel\RebootableInterface" is deprecated since Symfony 3.4 and will be unsupported in 4.0.';
            $realKernelClass = get_class($realKernel);
            $namespace = '';
            if (false !== $pos = strrpos($realKernelClass, '\\')) {
                $namespace = substr($realKernelClass, 0, $pos);
                $realKernelClass = substr($realKernelClass, $pos + 1);
            }
            $tempKernel = $this->getTempKernel($realKernel, $namespace, $realKernelClass, $warmupDir);
            $tempKernel->boot();

            $tempKernelReflection = new \ReflectionObject($tempKernel);
            $tempKernelFile = $tempKernelReflection->getFileName();
        }

        // warmup temporary dir
        $warmer = $tempKernel->getContainer()->get('cache_warmer');
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

        if ($realKernel instanceof RebootableInterface) {
            return;
        }

        // fix references to the Kernel in .meta files
        $safeTempKernel = str_replace('\\', '\\\\', get_class($tempKernel));
        $realKernelFQN = get_class($realKernel);

        foreach (Finder::create()->files()->depth('<3')->name('*.meta')->in($warmupDir) as $file) {
            file_put_contents($file, preg_replace(
                '/(C\:\d+\:)"'.$safeTempKernel.'"/',
                sprintf('$1"%s"', $realKernelFQN),
                file_get_contents($file)
            ));
        }

        // fix references to container's class
        $tempContainerClass = $tempKernel->getContainerClass();
        $realContainerClass = $tempKernel->getRealContainerClass();
        foreach (Finder::create()->files()->depth('<2')->name($tempContainerClass.'*')->in($warmupDir) as $file) {
            $content = str_replace($tempContainerClass, $realContainerClass, file_get_contents($file));
            file_put_contents($file, $content);
            rename($file, str_replace(DIRECTORY_SEPARATOR.$tempContainerClass, DIRECTORY_SEPARATOR.$realContainerClass, $file));
        }
        if (is_dir($tempContainerDir = $warmupDir.'/'.get_class($tempKernel->getContainer()))) {
            foreach (Finder::create()->files()->in($tempContainerDir) as $file) {
                $content = str_replace($tempContainerClass, $realContainerClass, file_get_contents($file));
                file_put_contents($file, $content);
            }
        }

        // remove temp kernel file after cache warmed up
        @unlink($tempKernelFile);
    }

    /**
     * @param KernelInterface $parent
     * @param string          $namespace
     * @param string          $parentClass
     * @param string          $warmupDir
     *
     * @return KernelInterface
     */
    protected function getTempKernel(KernelInterface $parent, $namespace, $parentClass, $warmupDir)
    {
        $projectDir = '';
        $cacheDir = var_export($warmupDir, true);
        $rootDir = var_export(realpath($parent->getRootDir()), true);
        $logDir = var_export(realpath($parent->getLogDir()), true);
        // the temp kernel class name must have the same length than the real one
        // to avoid the many problems in serialized resources files
        $class = substr($parentClass, 0, -1).'_';
        // the temp container class must be changed too
        $container = $parent->getContainer();
        $realContainerClass = var_export($container->hasParameter('kernel.container_class') ? $container->getParameter('kernel.container_class') : get_class($parent->getContainer()), true);
        $containerClass = substr_replace($realContainerClass, '_', -2, 1);

        if (method_exists($parent, 'getProjectDir')) {
            $projectDir = var_export(realpath($parent->getProjectDir()), true);
            $projectDir = <<<EOF
        public function getProjectDir()
        {
            return $projectDir;
        }
        
EOF;
        }

        $code = <<<EOF
<?php

namespace $namespace
{
    class $class extends $parentClass
    {
        public function getCacheDir()
        {
            return $cacheDir;
        }

        public function getRootDir()
        {
            return $rootDir;
        }

        $projectDir
        public function getLogDir()
        {
            return $logDir;
        }

        public function getRealContainerClass()
        {
            return $realContainerClass;
        }

        public function getContainerClass()
        {
            return $containerClass;
        }

        protected function buildContainer()
        {
            \$container = parent::buildContainer();

            // filter container's resources, removing reference to temp kernel file
            \$resources = \$container->getResources();
            \$filteredResources = array();
            foreach (\$resources as \$resource) {
                if ((string) \$resource !== __FILE__) {
                    \$filteredResources[] = \$resource;
                }
            }

            \$container->setResources(\$filteredResources);

            return \$container;
        }
    }
}
EOF;
        $this->filesystem->mkdir($warmupDir);
        file_put_contents($file = $warmupDir.'/kernel.tmp', $code);
        require_once $file;
        $class = "$namespace\\$class";

        return new $class($parent->getEnvironment(), $parent->isDebug());
    }
}
