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
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;

/**
 * A console command for dumping available configuration reference.
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 * @author Wouter J <waldio.webdesign@gmail.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
abstract class AbstractConfigCommand extends ContainerDebugCommand
{
    protected function listBundles(OutputInterface $output)
    {
        $output->writeln('Available registered bundles with their extension alias if available:');

        $table = $this->getHelperSet()->get('table');
        $table->setHeaders(array('Bundle name', 'Extension alias'));
        foreach ($this->getContainer()->get('kernel')->getBundles() as $bundle) {
            $extension = $bundle->getContainerExtension();
            $table->addRow(array($bundle->getName(), $extension ? $extension->getAlias() : ''));
        }

        $table->render($output);
    }

    protected function findExtension($name)
    {
        $extension = null;

        $bundles = $this->getContainer()->get('kernel')->getBundles();

        if (preg_match('/Bundle$/', $name)) {
            // input is bundle name

            if (isset($bundles[$name])) {
                $extension = $bundles[$name]->getContainerExtension();
            }

            if (!$extension) {
                throw new \LogicException(sprintf('No extensions with configuration available for "%s"', $name));
            }
        } else {
            foreach ($bundles as $bundle) {
                $extension = $bundle->getContainerExtension();

                if ($extension && $name === $extension->getAlias()) {
                    break;
                }

                $extension = null;
            }

            if (!$extension) {
                throw new \LogicException(sprintf('No extension with alias "%s" is enabled', $name));
            }
        }

        return $extension;
    }

    public function validateConfiguration(Extension $extension, $configuration)
    {
        if (!$configuration) {
            throw new \LogicException(sprintf('The extension with alias "%s" does not have its getConfiguration() method setup', $extension->getAlias()));
        }

        if (!$configuration instanceof ConfigurationInterface) {
            throw new \LogicException(sprintf('Configuration class "%s" should implement ConfigurationInterface in order to be dumpable', get_class($configuration)));
        }
    }
}
