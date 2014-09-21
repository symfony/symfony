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

use Symfony\Component\Config\Definition\ReferenceDumper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * A console command for dumping available configuration reference
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 */
class ConfigDumpReferenceCommand extends ContainerDebugCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('config:dump-reference')
            ->setDefinition(array(
                new InputArgument('name', InputArgument::OPTIONAL, 'The Bundle or extension alias'),
            ))
            ->setDescription('Dumps default configuration for an extension')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command dumps the default configuration for an extension/bundle.

The extension alias or bundle name can be used:

Example:

  <info>php %command.full_name% framework</info>

or

  <info>php %command.full_name% FrameworkBundle</info>
EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \LogicException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $bundles = $this->getContainer()->get('kernel')->getBundles();
        $containerBuilder = $this->getContainerBuilder();

        $name = $input->getArgument('name');

        if (empty($name)) {
            $output->writeln('Available registered bundles with their extension alias if available:');
            foreach ($bundles as $bundle) {
                $extension = $bundle->getContainerExtension();
                $output->writeln($bundle->getName().($extension ? ': '.$extension->getAlias() : ''));
            }

            return;
        }

        $extension = null;

        if (preg_match('/Bundle$/', $name)) {
            // input is bundle name

            if (isset($bundles[$name])) {
                $extension = $bundles[$name]->getContainerExtension();
            }

            if (!$extension) {
                throw new \LogicException(sprintf('No extensions with configuration available for "%s"', $name));
            }

            $message = 'Default configuration for "'.$name.'"';
        } else {
            foreach ($bundles as $bundle) {
                $extension = $bundle->getContainerExtension();

                if ($extension && $extension->getAlias() === $name) {
                    break;
                }

                $extension = null;
            }

            if (!$extension) {
                throw new \LogicException(sprintf('No extension with alias "%s" is enabled', $name));
            }

            $message = 'Default configuration for extension with alias: "'.$name.'"';
        }

        $configuration = $extension->getConfiguration(array(), $containerBuilder);

        if (!$configuration) {
            throw new \LogicException(sprintf('The extension with alias "%s" does not have it\'s getConfiguration() method setup', $extension->getAlias()));
        }

        if (!$configuration instanceof ConfigurationInterface) {
            throw new \LogicException(sprintf('Configuration class "%s" should implement ConfigurationInterface in order to be dumpable', get_class($configuration)));
        }

        $output->writeln($message);

        $dumper = new ReferenceDumper();
        $output->writeln($dumper->dump($configuration));
    }
}
