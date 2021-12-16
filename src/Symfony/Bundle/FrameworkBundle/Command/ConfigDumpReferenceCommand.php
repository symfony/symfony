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
use Symfony\Component\Config\Definition\Dumper\XmlReferenceDumper;
use Symfony\Component\Config\Definition\Dumper\YamlReferenceDumper;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * A console command for dumping available configuration reference.
 *
 * @author Kevin Bond <kevinbond@gmail.com>
 * @author Wouter J <waldio.webdesign@gmail.com>
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 *
 * @final
 */
class ConfigDumpReferenceCommand extends AbstractConfigCommand
{
    protected static $defaultName = 'config:dump-reference';
    protected static $defaultDescription = 'Dump the default configuration for an extension';

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDefinition([
                new InputArgument('name', InputArgument::OPTIONAL, 'The Bundle name or the extension alias'),
                new InputArgument('path', InputArgument::OPTIONAL, 'The configuration option path'),
                new InputOption('format', null, InputOption::VALUE_REQUIRED, 'The output format (yaml or xml)', 'yaml'),
            ])
            ->setDescription(self::$defaultDescription)
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command dumps the default configuration for an
extension/bundle.

Either the extension alias or bundle name can be used:

  <info>php %command.full_name% framework</info>
  <info>php %command.full_name% FrameworkBundle</info>

With the <info>--format</info> option specifies the format of the configuration,
this is either <comment>yaml</comment> or <comment>xml</comment>.
When the option is not provided, <comment>yaml</comment> is used.

  <info>php %command.full_name% FrameworkBundle --format=xml</info>

For dumping a specific option, add its path as second argument (only available for the yaml format):

  <info>php %command.full_name% framework profiler.matcher</info>

EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     *
     * @throws \LogicException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $errorIo = $io->getErrorStyle();

        if (null === $name = $input->getArgument('name')) {
            $this->listBundles($errorIo);

            $kernel = $this->getApplication()->getKernel();
            if ($kernel instanceof ExtensionInterface
                && ($kernel instanceof ConfigurationInterface || $kernel instanceof ConfigurationExtensionInterface)
                && $kernel->getAlias()
            ) {
                $errorIo->table(['Kernel Extension'], [[$kernel->getAlias()]]);
            }

            $errorIo->comment([
                'Provide the name of a bundle as the first argument of this command to dump its default configuration. (e.g. <comment>config:dump-reference FrameworkBundle</comment>)',
                'For dumping a specific option, add its path as the second argument of this command. (e.g. <comment>config:dump-reference FrameworkBundle profiler.matcher</comment> to dump the <comment>framework.profiler.matcher</comment> configuration)',
            ]);

            return 0;
        }

        $extension = $this->findExtension($name);

        if ($extension instanceof ConfigurationInterface) {
            $configuration = $extension;
        } else {
            $configuration = $extension->getConfiguration([], $this->getContainerBuilder($this->getApplication()->getKernel()));
        }

        $this->validateConfiguration($extension, $configuration);

        $format = $input->getOption('format');

        if ('yaml' === $format && !class_exists(Yaml::class)) {
            $errorIo->error('Setting the "format" option to "yaml" requires the Symfony Yaml component. Try running "composer install symfony/yaml" or use "--format=xml" instead.');

            return 1;
        }

        $path = $input->getArgument('path');

        if (null !== $path && 'yaml' !== $format) {
            $errorIo->error('The "path" option is only available for the "yaml" format.');

            return 1;
        }

        if ($name === $extension->getAlias()) {
            $message = sprintf('Default configuration for extension with alias: "%s"', $name);
        } else {
            $message = sprintf('Default configuration for "%s"', $name);
        }

        if (null !== $path) {
            $message .= sprintf(' at path "%s"', $path);
        }

        switch ($format) {
            case 'yaml':
                $io->writeln(sprintf('# %s', $message));
                $dumper = new YamlReferenceDumper();
                break;
            case 'xml':
                $io->writeln(sprintf('<!-- %s -->', $message));
                $dumper = new XmlReferenceDumper();
                break;
            default:
                $io->writeln($message);
                throw new InvalidArgumentException('Only the yaml and xml formats are supported.');
        }

        $io->writeln(null === $path ? $dumper->dump($configuration, $extension->getNamespace()) : $dumper->dumpAtPath($configuration, $path));

        return 0;
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('name')) {
            $suggestions->suggestValues($this->getAvailableBundles());
        }

        if ($input->mustSuggestOptionValuesFor('format')) {
            $suggestions->suggestValues($this->getAvailableFormatOptions());
        }
    }

    private function getAvailableBundles(): array
    {
        $bundles = [];

        foreach ($this->getApplication()->getKernel()->getBundles() as $bundle) {
            $bundles[] = $bundle->getName();
            $bundles[] = $bundle->getContainerExtension()->getAlias();
        }

        return $bundles;
    }

    private function getAvailableFormatOptions(): array
    {
        return ['yaml', 'xml'];
    }
}
