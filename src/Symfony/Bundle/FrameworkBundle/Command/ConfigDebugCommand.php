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
use Symfony\Component\Console\Style\SymfonyStyle;
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
            ->setDefinition(array(
                new InputArgument('name', InputArgument::OPTIONAL, 'The bundle name or the extension alias'),
            ))
            ->setDescription('Dumps the current configuration for an extension')
            ->setHelp(<<<'EOF'
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
        $io = new SymfonyStyle($input, $output);

        if (null === $name = $input->getArgument('name')) {
            $this->listBundles($io);
            $io->comment('Provide the name of a bundle as the first argument of this command to dump its configuration. (e.g. <comment>debug:config FrameworkBundle</comment>)');

            return;
        }

        $extension = $this->findExtension($name);
        $container = $this->compileContainer();

        $configs = $container->getExtensionConfig($extension->getAlias());
        $configuration = $extension->getConfiguration($configs, $container);

        $this->validateConfiguration($extension, $configuration);

        $configs = $container->getParameterBag()->resolveValue($configs);

        $processor = new Processor();
        $config = $processor->processConfiguration($configuration, $configs);

        if ($name === $extension->getAlias()) {
            $io->title(sprintf('Current configuration for extension with alias "%s"', $name));
        } else {
            $io->title(sprintf('Current configuration for "%s"', $name));
        }

        $io->writeln(Yaml::dump(array($extension->getAlias() => $container->getParameterBag()->resolveValue($config)), 10));
    }

    private function compileContainer()
    {
        $kernel = clone $this->getContainer()->get('kernel');
        $kernel->boot();

        $method = new \ReflectionMethod($kernel, 'buildContainer');
        $method->setAccessible(true);
        $container = $method->invoke($kernel);
        $container->getCompiler()->compile($container);

        return $container;
    }
}
