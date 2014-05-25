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

/**
 * A console command for dumping available configuration reference.
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 * @author Wouter J <waldio.webdesign@gmail.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 * @author Daniel Espendiller <daniel@espendiller.net>
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
                new InputArgument('name', InputArgument::OPTIONAL, 'The Bundle name, the extension alias or * for all Bundles'),
                new InputOption('format', null, InputOption::VALUE_REQUIRED, 'The format, either yaml or xml', 'yaml'),
            ))
            ->setDescription('Dumps the default configuration for an extension')
            ->setHelp(<<<EOF
The <info>%command.name%</info> command dumps the default configuration for an
extension/bundle.

Either the extension alias or bundle name can be used:

  <info>php %command.full_name% framework</info>
  <info>php %command.full_name% FrameworkBundle</info>
  <info>php %command.full_name% *</info>

With the <info>format</info> option specifies the format of the configuration,
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
        $name = $input->getArgument('name');

        if (empty($name)) {
            $this->listBundles($output);

            return;
        }

        if ($name === '*') {
            $this->dumpAll($input->getOption('format'), $output);

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

        $dumper = $this->getDumper($input->getOption('format'), $output, $message);

        $output->writeln($dumper->dump($configuration, $extension->getNamespace()));
    }

    protected function getDumper($format, OutputInterface $output, $message = "") {

        switch ($format) {
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

        return $dumper;
    }

    protected function dumpAll($format, OutputInterface $output) {

        $message = sprintf('Dumping default configuration for all bundles');
        $dumper = $this->getDumper($format, $output, $message);

        if ($format === 'xml') {
            $output->writeln('<config>');
        }

        foreach ($this->getContainer()->get('kernel')->getBundles() as $bundle) {
            $extension = $bundle->getContainerExtension();
            if ($extension) {

                $configuration = $extension->getConfiguration(array(), $this->getContainerBuilder());
                try {
                    $this->validateConfiguration($extension, $configuration);
                    $output->writeln($dumper->dump($configuration, $extension->getNamespace(), $extension->getAlias()));
                } catch (\LogicException $e) {
                }

            }
        }

        if ($format === 'xml') {
            $output->writeln('</config>');
        }

        return;
    }
}
