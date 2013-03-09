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
     * @see Command
     */
    protected function configure()
    {
        $this
            ->setName('config:dump-reference')
            ->setDefinition(array(
                new InputArgument('name', InputArgument::REQUIRED, 'The Bundle or extension alias')
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
     * @see Command
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $bundles = $this->getContainer()->get('kernel')->getBundles();
        $containerBuilder = $this->getContainerBuilder();

        $name = $input->getArgument('name');

        $extension = null;

        if (preg_match('/Bundle$/', $name)) {
            // input is bundle name

            if (isset($bundles[$name])) {
                $extension = $bundles[$name]->getContainerExtension();
            }

            if (!$extension) {
                throw new \LogicException('No extensions with configuration available for "'.$name.'"');
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
                throw new \LogicException('No extension with alias "'.$name.'" is enabled');
            }

            $message = 'Default configuration for extension with alias: "'.$name.'"';
        }

        $configuration = $extension->getConfiguration(array(), $containerBuilder);

        if (!$configuration) {
            throw new \LogicException('The extension with alias "'.$extension->getAlias().
                    '" does not have it\'s getConfiguration() method setup');
        }

        if (!$configuration instanceof ConfigurationInterface) {
            throw new \LogicException(
                'Configuration class "'.get_class($configuration).
                '" should implement ConfigurationInterface in order to be dumpable');
        }

        $output->writeln($message);

        $dumper = new ReferenceDumper();
        $output->writeln($dumper->dump($configuration));
    }
}
