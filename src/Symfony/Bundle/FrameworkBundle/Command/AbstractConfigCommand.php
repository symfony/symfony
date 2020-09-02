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
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\StyleInterface;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
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
    /**
     * @param OutputInterface|StyleInterface $output
     */
    protected function listBundles($output)
    {
        $title = 'Available registered bundles with their extension alias if available';
        $headers = ['Bundle name', 'Extension alias'];
        $rows = [];

        $bundles = $this->getApplication()->getKernel()->getBundles();
        usort($bundles, function ($bundleA, $bundleB) {
            return strcmp($bundleA->getName(), $bundleB->getName());
        });

        foreach ($bundles as $bundle) {
            $extension = $bundle->getContainerExtension();
            $rows[] = [$bundle->getName(), $extension ? $extension->getAlias() : ''];
        }

        if ($output instanceof StyleInterface) {
            $output->title($title);
            $output->table($headers, $rows);
        } else {
            $output->writeln($title);
            $table = new Table($output);
            $table->setHeaders($headers)->setRows($rows)->render();
        }
    }

    /**
     * @return ExtensionInterface
     */
    protected function findExtension(string $name)
    {
        $bundles = $this->initializeBundles();
        $minScore = \INF;

        $kernel = $this->getApplication()->getKernel();
        if ($kernel instanceof ExtensionInterface && ($kernel instanceof ConfigurationInterface || $kernel instanceof ConfigurationExtensionInterface)) {
            if ($name === $kernel->getAlias()) {
                return $kernel;
            }

            if ($kernel->getAlias()) {
                $distance = levenshtein($name, $kernel->getAlias());

                if ($distance < $minScore) {
                    $guess = $kernel->getAlias();
                    $minScore = $distance;
                }
            }
        }

        foreach ($bundles as $bundle) {
            if ($name === $bundle->getName()) {
                if (!$bundle->getContainerExtension()) {
                    throw new \LogicException(sprintf('Bundle "%s" does not have a container extension.', $name));
                }

                return $bundle->getContainerExtension();
            }

            $distance = levenshtein($name, $bundle->getName());

            if ($distance < $minScore) {
                $guess = $bundle->getName();
                $minScore = $distance;
            }

            $extension = $bundle->getContainerExtension();

            if ($extension) {
                if ($name === $extension->getAlias()) {
                    return $extension;
                }

                $distance = levenshtein($name, $extension->getAlias());

                if ($distance < $minScore) {
                    $guess = $extension->getAlias();
                    $minScore = $distance;
                }
            }
        }

        if ('Bundle' !== substr($name, -6)) {
            $message = sprintf('No extensions with configuration available for "%s".', $name);
        } else {
            $message = sprintf('No extension with alias "%s" is enabled.', $name);
        }

        if (isset($guess) && $minScore < 3) {
            $message .= sprintf("\n\nDid you mean \"%s\"?", $guess);
        }

        throw new LogicException($message);
    }

    public function validateConfiguration(ExtensionInterface $extension, $configuration)
    {
        if (!$configuration) {
            throw new \LogicException(sprintf('The extension with alias "%s" does not have its getConfiguration() method setup.', $extension->getAlias()));
        }

        if (!$configuration instanceof ConfigurationInterface) {
            throw new \LogicException(sprintf('Configuration class "%s" should implement ConfigurationInterface in order to be dumpable.', get_debug_type($configuration)));
        }
    }

    private function initializeBundles()
    {
        // Re-build bundle manually to initialize DI extensions that can be extended by other bundles in their build() method
        // as this method is not called when the container is loaded from the cache.
        $container = $this->getContainerBuilder();
        $bundles = $this->getApplication()->getKernel()->getBundles();
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
