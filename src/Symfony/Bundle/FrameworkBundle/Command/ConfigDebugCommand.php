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

use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * A console command for dumping available configuration reference.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class ConfigDebugCommand extends AbstractConfigCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('debug:config')
            ->setAliases(array(
                'config:debug',
            ))
            ->setDefinition(array(
                new InputArgument('name', InputArgument::OPTIONAL, 'The bundle name or the extension alias'),
            ))
            ->setDescription('Dumps the current configuration for an extension')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command dumps the current configuration for an
extension/bundle.

Either the extension alias or bundle name can be used:

  <info>php %command.full_name% framework</info>
  <info>php %command.full_name% FrameworkBundle</info>

EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $name = $input->getArgument('name');

        if (empty($name)) {
            $this->listBundles($output);

            return;
        }

        $extension = $this->findExtension($name);

        $kernel = $this->getContainer()->get('kernel');
        $method = new \ReflectionMethod($kernel, 'buildContainer');
        $method->setAccessible(true);
        $container = $method->invoke($kernel);

        $configs = $container->getExtensionConfig($extension->getAlias());
        $configuration = $extension->getConfiguration($configs, $container);

        $this->validateConfiguration($extension, $configuration);

        $configs = $container->getParameterBag()->resolveValue($configs);

        $processor = new Processor();
        $config = $processor->processConfiguration($configuration, $configs);

        if ($name === $extension->getAlias()) {
            $output->writeln(sprintf('# Current configuration for extension with alias: "%s"', $name));
        } else {
            $output->writeln(sprintf('# Current configuration for "%s"', $name));
        }

        $output->writeln(Yaml::dump(array($extension->getAlias() => $config), 3));
    }
}
