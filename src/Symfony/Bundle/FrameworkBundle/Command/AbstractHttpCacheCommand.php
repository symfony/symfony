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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\HttpCache\HttpCache;

abstract class AbstractHttpCacheCommand extends ContainerAwareCommand
{
    /**
     * @return array
     */
    protected function getDefinitionArray()
    {
        return array(
            new InputArgument('uri', InputArgument::OPTIONAL, 'A full uri, including the scheme, host, path and query string'),
            new InputOption('kernel_class_name', '', InputOption::VALUE_OPTIONAL, 'Name of the HttpCache kernel', 'AppCache'),
        );
    }

    /**
     * @return string cache dir
     * @throws \RuntimeException
     */
    protected function getCacheDir()
    {
        $cacheDir = $this->getContainer()->getParameter('kernel.cache_dir').'/http_cache';
        if (!is_readable($cacheDir)) {
            throw new \RuntimeException(sprintf('Unable to write in the "%s" directory', $cacheDir));
        }

        return $cacheDir;
    }

    /**
     * @param \Symfony\Component\Console\Input\InputInterface $input
     * @param \Symfony\Component\HttpKernel\KernelInterface $kernel
     * @return \Symfony\Component\HttpKernel\HttpCache\HttpCache
     * @throws \RuntimeException
     */
    protected function getCacheKernel(InputInterface $input, KernelInterface $kernel)
    {
        $kernelClassName = $input->getOption('kernel_class_name');
        if (!class_exists($kernelClassName)) {
            throw new \RuntimeException(sprintf('Kernel class "%s" not loadable', $kernelClassName));
        }

        $cacheKernel = new $kernelClassName($kernel);
        if (! $cacheKernel instanceof HttpCache) {
            throw new \RuntimeException(sprintf('Kernel ("%s") is not an instance of HttpCache', get_class($kernel)));
        }

        return $cacheKernel;
    }
}
