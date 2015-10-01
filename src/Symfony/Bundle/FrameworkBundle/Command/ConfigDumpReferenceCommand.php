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

use Symfony\Component\Config\Definition\Dumper\YamlReferenceDumper;
use Symfony\Component\Config\Definition\Dumper\XmlReferenceDumper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * A console command for dumping available configuration reference.
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 * @author Wouter J <waldio.webdesign@gmail.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class ConfigDumpReferenceCommand extends AbstractConfigCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('config:dump-reference')
            ->setDefinition(array(
                new InputArgument('name', InputArgument::OPTIONAL, 'The Bundle name or the extension alias'),
                new InputOption('format', null, InputOption::VALUE_REQUIRED, 'The output format (yaml or xml)', 'yaml'),
            ))
            ->setDescription('Dumps the default configuration for an extension')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command dumps the default configuration for an
extension/bundle.

Either the extension alias or bundle name can be used:

  <info>php %command.full_name% framework</info>
  <info>php %command.full_name% FrameworkBundle</info>

With the <info>--format</info> option specifies the format of the configuration,
this is either <comment>yaml</comment> or <comment>xml</comment>.
When the option is not provided, <comment>yaml</comment> is used.

  <info>php %command.full_name% FrameworkBundle --format=xml</info>

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
        $output = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');

        if (empty($name)) {
            $output->comment('Provide the name of a bundle as the first argument of this command to dump its default configuration.');
            $output->newLine();
            $this->listBundles($output);

            return;
        }

        $extension = $this->findExtension($name);

        $configuration = $extension->getConfiguration(array(), $this->getContainerBuilder());

        $this->validateConfiguration($extension, $configuration);

        if ($name === $extension->getAlias()) {
            $message = sprintf('Default configuration for extension with alias: "%s"', $name);
        } else {
            $message = sprintf('Default configuration for "%s"', $name);
        }

        switch ($input->getOption('format')) {
            case 'yaml':
                $output->writeln(sprintf('# %s', $message));
                $dumper = new YamlReferenceDumper();
                break;
            case 'xml':
                $output->writeln(sprintf('<!-- %s -->', $message));
                $dumper = new XmlReferenceDumper();
                break;
            default:
                $output->writeln($message);
                throw new \InvalidArgumentException('Only the yaml and xml formats are supported.');
        }

        $output->writeln($dumper->dump($configuration, $extension->getNamespace()));
    }
}
