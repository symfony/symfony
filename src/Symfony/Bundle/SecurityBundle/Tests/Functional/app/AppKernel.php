<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\SecurityBundle\Tests\Functional\app;

use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel;

/**
 * App Test Kernel for functional tests.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class AppKernel extends Kernel
{
    private $varDir;
    private $testCase;
    private $rootConfig;

    public function __construct($varDir, $testCase, $rootConfig, $environment, $debug)
    {
        if (!is_dir(__DIR__.'/'.$testCase)) {
            throw new \InvalidArgumentException(sprintf('The test case "%s" does not exist.', $testCase));
        }
        $this->varDir = $varDir;
        $this->testCase = $testCase;

        $fs = new Filesystem();
        foreach ((array) $rootConfig as $config) {
            if (!$fs->isAbsolutePath($config) && !is_file($config = __DIR__.'/'.$testCase.'/'.$config)) {
                throw new \InvalidArgumentException(sprintf('The root config "%s" does not exist.', $config));
            }

            $this->rootConfig[] = $config;
        }

        parent::__construct($environment, $debug);
    }

    public function getContainerClass(): string
    {
        return parent::getContainerClass().substr(md5(implode('', $this->rootConfig)), -16);
    }

    public function registerBundles(): iterable
    {
        if (!is_file($filename = $this->getProjectDir().'/'.$this->testCase.'/bundles.php')) {
            throw new \RuntimeException(sprintf('The bundles file "%s" does not exist.', $filename));
        }

        return include $filename;
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/'.$this->varDir.'/'.$this->testCase.'/cache/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/'.$this->varDir.'/'.$this->testCase.'/logs';
    }

    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        foreach ($this->rootConfig as $config) {
            $loader->load($config);
        }
    }

    public function serialize()
    {
        return serialize([$this->varDir, $this->testCase, $this->rootConfig, $this->getEnvironment(), $this->isDebug()]);
    }

    public function unserialize($str)
    {
        $a = unserialize($str);
        $this->__construct($a[0], $a[1], $a[2], $a[3], $a[4]);
    }

    protected function getKernelParameters(): array
    {
        $parameters = parent::getKernelParameters();
        $parameters['kernel.test_case'] = $this->testCase;

        return $parameters;
    }
}
