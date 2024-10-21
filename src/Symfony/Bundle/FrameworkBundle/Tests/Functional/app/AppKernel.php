<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional\app;

use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Tests\Functional\Extension\TestDumpExtension;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel;

/**
 * App Test Kernel for functional tests.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class AppKernel extends Kernel implements ExtensionInterface, ConfigurationInterface
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
        if (!$fs->isAbsolutePath($rootConfig) && !file_exists($rootConfig = __DIR__.'/'.$testCase.'/'.$rootConfig)) {
            throw new \InvalidArgumentException(sprintf('The root config "%s" does not exist.', $rootConfig));
        }
        $this->rootConfig = $rootConfig;

        parent::__construct($environment, $debug);
    }

    protected function getContainerClass(): string
    {
        return parent::getContainerClass().substr(md5($this->rootConfig), -16);
    }

    public function registerBundles(): iterable
    {
        if (!file_exists($filename = $this->getProjectDir().'/'.$this->testCase.'/bundles.php')) {
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

    public function registerContainerConfiguration(LoaderInterface $loader)
    {
        $loader->load($this->rootConfig);
    }

    protected function build(ContainerBuilder $container)
    {
        $container->register('logger', NullLogger::class);
        $container->registerExtension(new TestDumpExtension());
    }

    public function __sleep(): array
    {
        return ['varDir', 'testCase', 'rootConfig', 'environment', 'debug'];
    }

    public function __wakeup()
    {
        foreach ($this as $k => $v) {
            if (\is_object($v)) {
                throw new \BadMethodCallException('Cannot unserialize '.__CLASS__);
            }
        }

        $this->__construct($this->varDir, $this->testCase, $this->rootConfig, $this->environment, $this->debug);
    }

    protected function getKernelParameters(): array
    {
        $parameters = parent::getKernelParameters();
        $parameters['kernel.test_case'] = $this->testCase;

        return $parameters;
    }

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('foo');
        $rootNode = $treeBuilder->getRootNode();
        $rootNode->children()->scalarNode('foo')->defaultValue('bar')->end()->end();

        return $treeBuilder;
    }

    public function load(array $configs, ContainerBuilder $container)
    {
    }

    public function getNamespace(): string
    {
        return '';
    }

    public function getXsdValidationBasePath()
    {
        return false;
    }

    public function getAlias(): string
    {
        return 'foo';
    }
}
