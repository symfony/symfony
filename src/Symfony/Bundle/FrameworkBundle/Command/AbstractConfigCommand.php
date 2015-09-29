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

use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Style\StyleInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

/**
 * A console command for dumping available configuration reference.
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 * @author Wouter J <waldio.webdesign@gmail.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
abstract class AbstractConfigCommand extends ContainerDebugCommand
{
    protected function listBundles($output)
    {
        $headers = array('Bundle name', 'Extension alias');
        $rows = array();
        foreach ($this->getContainer()->get('kernel')->getBundles() as $bundle) {
            $extension = $bundle->getContainerExtension();
            $rows[] = array($bundle->getName(), $extension ? $extension->getAlias() : '');
        }

        if ($output instanceof StyleInterface) {
            $output->table($headers, $rows);
        } else {
            $output->writeln('Available registered bundles with their extension alias if available:');
            $table = new Table($output);
            $table->setHeaders($headers)->setRows($rows)->render($output);
        }
    }

    protected function findExtension($name)
    {
        $extension = null;
        $bundles = $this->initializeBundles();
        foreach ($bundles as $bundle) {
            $extension = $bundle->getContainerExtension();

            if ($extension && ($name === $extension->getAlias() || $name === $bundle->getName())) {
                break;
            }
        }

        if (!$extension) {
            $message = sprintf('No extension with alias "%s" is enabled', $name);
            if (preg_match('/Bundle$/', $name)) {
                $message = sprintf('No extensions with configuration available for "%s"', $name);
            }

            throw new \LogicException($message);
        }

        return $extension;
    }

    public function validateConfiguration(ExtensionInterface $extension, $configuration)
    {
        if (!$configuration) {
            throw new \LogicException(sprintf('The extension with alias "%s" does not have its getConfiguration() method setup', $extension->getAlias()));
        }

        if (!$configuration instanceof ConfigurationInterface) {
            throw new \LogicException(sprintf('Configuration class "%s" should implement ConfigurationInterface in order to be dumpable', get_class($configuration)));
        }
    }

    private function initializeBundles()
    {
        // Re-build bundle manually to initialize DI extensions that can be extended by other bundles in their build() method
        // as this method is not called when the container is loaded from the cache.
        $container = $this->getContainerBuilder();
        $bundles = $this->getContainer()->get('kernel')->registerBundles();
        foreach ($bundles as $bundle) {
            if ($extension = $bundle->getContainerExtension()) {
                $container->registerExtension($extension);
            }
        }

        foreach ($bundles as $bundle) {
            $bundle->build($container);
        }

        return $bundles;
    }
}
