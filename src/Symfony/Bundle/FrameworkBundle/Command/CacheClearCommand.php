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
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Finder\Finder;

/**
 * Clear and Warmup the cache.
 *
 * @author Francis Besset <francis.besset@gmail.com>
 * @author Fabien Potencier <fabien@symfony.com>
 */
class CacheClearCommand extends ContainerAwareCommand
{
    protected $name;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('cache:clear')
            ->setDefinition(array(
                new InputOption('no-warmup', '', InputOption::VALUE_NONE, 'Do not warm up the cache'),
                new InputOption('no-optional-warmers', '', InputOption::VALUE_NONE, 'Skip optional cache warmers (faster)'),
            ))
            ->setDescription('Clears the cache')
            ->setHelp(<<<EOF
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
        $realCacheDir = $this->getContainer()->getParameter('kernel.cache_dir');
        $oldCacheDir  = $realCacheDir.'_old';

        if (!is_writable($realCacheDir)) {
            throw new \RuntimeException(sprintf('Unable to write in the "%s" directory', $realCacheDir));
        }

        $filesystem = $this->getContainer()->get('filesystem');
        $kernel = $this->getContainer()->get('kernel');
        $output->writeln(sprintf('Clearing the cache for the <info>%s</info> environment with debug <info>%s</info>', $kernel->getEnvironment(), var_export($kernel->isDebug(), true)));

        $this->getContainer()->get('cache_clearer')->clear($realCacheDir);

        if ($filesystem->exists($oldCacheDir)) {
            $filesystem->remove($oldCacheDir);
        }

        if ($input->getOption('no-warmup')) {
            $filesystem->rename($realCacheDir, $oldCacheDir);
        } else {
            $warmupDir = $realCacheDir.'_new';

            if ($filesystem->exists($warmupDir)) {
                $filesystem->remove($warmupDir);
            }

            $this->warmup($warmupDir, !$input->getOption('no-optional-warmers'));

            $filesystem->rename($realCacheDir, $oldCacheDir);
            $filesystem->rename($warmupDir, $realCacheDir);
        }

        $filesystem->remove($oldCacheDir);
    }

    protected function warmup($warmupDir, $enableOptionalWarmers = true)
    {
        $this->getContainer()->get('filesystem')->remove($warmupDir);

        $parent = $this->getContainer()->get('kernel');
        $class = get_class($parent);
        $namespace = '';
        if (false !== $pos = strrpos($class, '\\')) {
            $namespace = substr($class, 0, $pos);
            $class = substr($class, $pos + 1);
        }

        $kernel = $this->getTempKernel($parent, $namespace, $class, $warmupDir);
        $kernel->boot();

        $warmer = $kernel->getContainer()->get('cache_warmer');

        if ($enableOptionalWarmers) {
            $warmer->enableOptionalWarmers();
        }

        $warmer->warmUp($warmupDir);

        foreach (Finder::create()->files()->name('*.meta')->in($warmupDir) as $file) {
            // fix meta references to the Kernel
            $content = preg_replace(
                '/C\:\d+\:"'.preg_quote($class.$this->getTempKernelSuffix(), '"/').'"/',
                sprintf('C:%s:"%s"', strlen($class), $class),
                file_get_contents($file)
            );

            // fix meta references to cache files
            $realWarmupDir = substr($warmupDir, 0, -4);
            $content = preg_replace_callback(
                '/s\:\d+\:"'.preg_quote($warmupDir, '/').'([^"]+)"/',
                function (array $matches) use ($realWarmupDir) {
                    $path = $realWarmupDir.$matches[1];
                    return sprintf('s:%s:"%s"', strlen($path), $path);
                },
                $content
            );

            file_put_contents($file, $content);
        }

        // fix container files and classes
        $regex = '/'.preg_quote($this->getTempKernelSuffix(), '/').'/';
        foreach (Finder::create()->files()->name(get_class($kernel->getContainer()).'*')->in($warmupDir) as $file) {
            $content = file_get_contents($file);
            $content = preg_replace($regex, '', $content);

            // fix absolute paths to the cache directory
            $content = preg_replace('/'.preg_quote($warmupDir, '/').'/', preg_replace('/_new$/', '', $warmupDir), $content);

            file_put_contents(preg_replace($regex, '', $file), $content);
            unlink($file);
        }
    }

    protected function getTempKernelSuffix()
    {
        if (null === $this->name) {
            $this->name = '__'.uniqid().'__';
        }

        return $this->name;
    }

    protected function getTempKernel(KernelInterface $parent, $namespace, $class, $warmupDir)
    {
        $suffix = $this->getTempKernelSuffix();
        $rootDir = $parent->getRootDir();
        $code = <<<EOF
<?php

namespace $namespace
{
    class $class$suffix extends $class
    {
        public function getCacheDir()
        {
            return '$warmupDir';
        }

        public function getRootDir()
        {
            return '$rootDir';
        }

        protected function getContainerClass()
        {
            return parent::getContainerClass().'$suffix';
        }
    }
}
EOF;
        $this->getContainer()->get('filesystem')->mkdir($warmupDir);
        file_put_contents($file = $warmupDir.'/kernel.tmp', $code);
        require_once $file;
        @unlink($file);
        $class = "$namespace\\$class$suffix";

        return new $class($parent->getEnvironment(), $parent->isDebug());
    }
}
