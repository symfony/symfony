<?php

namespace Symfony\Bundle\SecurityBundle\Tests\Functional;

use Symfony\Component\HttpKernel\Util\Filesystem;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\HttpKernel\Kernel;

/**
 * App Test Kernel for functional tests.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class AppKernel extends Kernel
{
    private $testCase;

    public function __construct($testCase, $environment, $debug)
    {
        if (!is_dir(__DIR__.'/'.$testCase)) {
            throw new \InvalidArgumentException(sprintf('The test case "%s" does not exist.', $testCase));
        }
        $this->testCase = $testCase;

        parent::__construct($environment, $debug);
    }

    public function registerBundles()
    {
        if (!file_exists($filename = $this->getRootDir().'/'.$this->testCase.'/bundles.php')) {
            throw new \RuntimeException(sprintf('The bundles file "%s" does not exist.', $filename));
        }

        return include $filename;
    }

    public function getRootDir()
    {
        return __DIR__;
    }

    public function getCacheDir()
    {
        return sys_get_temp_dir().'/'.$this->testCase.'/cache/'.$this->environment;
    }

    public function getLogDir()
    {
        return sys_get_temp_dir().'/'.$this->testCase.'/logs';
    }

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        if (!file_exists($filename = $this->getRootDir().'/'.$this->testCase.'/config.yml')) {
            throw new \RuntimeException(sprintf('The config file "%s" does not exist.', $filename));
        }

        $loader->load($filename);
    }

    protected function getKernelParameters()
    {
        $parameters = parent::getKernelParameters();
        $parameters['kernel.test_case'] = $this->testCase;

        return $parameters;
    }
}