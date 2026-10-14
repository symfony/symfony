<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\JsonSchema\Tests\Fixtures;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Kernel\AbstractKernel;
use Symfony\Component\DependencyInjection\Kernel\BundleInterface;
use Symfony\Component\DependencyInjection\Kernel\KernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\JsonSchema\JsonSchemaBundle;

class JsonSchemaBundleTestKernel extends AbstractKernel
{
    use KernelTrait;

    /**
     * @param list<BundleInterface>               $extraBundles
     * @param array<string, array<string, mixed>> $extensionConfigs
     */
    public function __construct(
        string $environment,
        private string $dir,
        private array $extraBundles,
        private array $extensionConfigs,
        private ?\Closure $buildContainer,
    ) {
        parent::__construct($environment, true);
    }

    public function getProjectDir(): string
    {
        return $this->dir;
    }

    public function registerBundles(): iterable
    {
        yield new JsonSchemaBundle();
        yield from $this->extraBundles;
    }

    protected function build(ContainerBuilder $container): void
    {
        $container->setAlias('test.json_schema.generator', 'json_schema.generator')
            ->setPublic(true);

        if (null !== $this->buildContainer) {
            ($this->buildContainer)($container);
        }
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        foreach ($this->extensionConfigs as $name => $config) {
            $container->extension($name, $config);
        }
    }
}
