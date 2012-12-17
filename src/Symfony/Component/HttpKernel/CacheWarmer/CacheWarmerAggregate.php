<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\HttpKernel\CacheWarmer;

use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Aggregates several cache warmers into a single one.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class CacheWarmerAggregate implements CacheWarmerInterface
{
    const NEW_CACHE_FOLDER_SUFFIX = '__new__';
    const OLD_CACHE_FOLDER_SUFFIX = '__old__';

    protected $warmers;
    protected $optionalsEnabled;
    protected $kernelSuffix;

    public function __construct(array $warmers = array())
    {
        $this->setWarmers($warmers);
        $this->optionalsEnabled = false;
    }

    /**
     * @param Boolean $enabled
     */
    public function enableOptionalWarmers($enabled = true)
    {
        $this->optionalsEnabled = (Boolean) $enabled;
    }

    /**
     * Warms up the cache.
     *
     * @param string $cacheDir The cache directory
     */
    public function warmUp($cacheDir)
    {
        foreach ($this->warmers as $warmer) {
            if (!$this->optionalsEnabled && $warmer->isOptional()) {
                continue;
            }

            $warmer->warmUp($cacheDir);
        }
    }

    /**
     * Checks whether this warmer is optional or not.
     *
     * @return Boolean always true
     */
    public function isOptional()
    {
        return false;
    }

    public function setWarmers(array $warmers)
    {
        $this->warmers = array();
        foreach ($warmers as $warmer) {
            $this->add($warmer);
        }
    }

    public function add(CacheWarmerInterface $warmer)
    {
        $this->warmers[] = $warmer;
    }

    /**
     * Warms-up a kernel according to the given configuration.
     *
     * @param KernelInterface  $kernel           The kernel
     * @param Filesystem       $fs               The filesystem
     * @param string           $cacheDir         The cache directory where the cache files will be generated
     * @param string           $env              The environment (ie 'prod', 'dev')
     * @param Boolean          $debug            The value of the %kernel.debug% parameter
     * @param Boolean          $optionalsEnabled Whether the optional warmers should be enabled
     * @param string|null      $tempCacheDir     A temporary working directory
     */
    public function warmUpForEnv(KernelInterface $kernel, FileSystem $fs, $cacheDir, $env, $debug, $optionalsEnabled, $tempCacheDir = null)
    {
        if (null === $tempCacheDir) {
            $tempCacheDir = rtrim($cacheDir, '/\\') . self::NEW_CACHE_FOLDER_SUFFIX;
        }

        $fs->remove($tempCacheDir);

        $class = get_class($kernel);
        $namespace = '';
        if (false !== $pos = strrpos($class, '\\')) {
            $namespace = substr($class, 0, $pos);
            $class = substr($class, $pos + 1);
        }

        $tempKernel = $this->getTempKernel($kernel, $namespace, $class, $tempCacheDir, $env, $debug, $fs);
        $tempKernel->boot();

        $warmer = $tempKernel->getContainer()->get('cache_warmer');
        $warmer->enableOptionalWarmers($optionalsEnabled);
        $warmer->warmUp($tempCacheDir);

        // fix container files and classes
        $regex = '/' . preg_quote($this->getTempKernelSuffix(), '/') . '/';
        foreach (Finder::create()->files()->name(get_class($tempKernel->getContainer()) . '*')->in($tempCacheDir) as $file) {
            $content = preg_replace($regex, '', $file->getContents());

            // fix absolute paths to the cache directory
            $content = preg_replace(
                '/'.preg_quote($tempCacheDir, '/').'/',
                preg_replace('/' . self::NEW_CACHE_FOLDER_SUFFIX . '$/', '', $tempCacheDir),
                $content
            );

            file_put_contents(preg_replace($regex, '', $file), $content);
            $fs->remove($file);
        }

        $from = '/C:\d+:"' . preg_quote($class . $this->getTempKernelSuffix(), '/').'"/';
        $to = sprintf('C:%d:"%s"', strlen($class), $class);

        foreach (Finder::create()->files()->name('*.meta')->in($tempCacheDir) as $file) {
            // Fix references to the kernel
            $file->putContents(preg_replace($from, $to, $file->getContents()));
        }

        $oldCacheDir = rtrim($cacheDir, '/\\') . self::OLD_CACHE_FOLDER_SUFFIX;
        $fs
            ->rename($cacheDir, $oldCacheDir)
            ->rename($tempCacheDir, $cacheDir)
            ->remove($oldCacheDir)
        ;
    }

    /**
     * @return string A unique suffix used to identify the temporary kernel
     */
    protected function getTempKernelSuffix()
    {
        if (null === $this->kernelSuffix) {
            $this->kernelSuffix = '__' . uniqid() . '__';
        }

        return $this->kernelSuffix;
    }

    protected function getTempKernel(KernelInterface $parent, $namespace, $class, $warmupDir, $env, $debug, FileSystem $fs)
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
        $fs->mkdir($warmupDir);
        file_put_contents($file = $warmupDir.'/kernel.tmp', $code);
        require_once $file;
        $fs->remove($file);
        $class = "$namespace\\$class$suffix";

        return new $class($env, $debug);
    }
}
