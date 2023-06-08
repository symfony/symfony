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
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
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
#[AsCommand(name: 'config:dump-reference', description: 'Dump the default configuration for an extension')]
class ConfigDumpReferenceCommand extends AbstractConfigCommand
{
    protected function configure(): void
    {
        $commentedHelpFormats = array_map(static fn (string $format): string => sprintf('<comment>%s</comment>', $format), $this->getAvailableFormatOptions());
        $helpFormats = implode('", "', $commentedHelpFormats);

        $this
            ->setDefinition([
                new InputArgument('name', InputArgument::OPTIONAL, 'The Bundle name or the extension alias'),
                new InputArgument('path', InputArgument::OPTIONAL, 'The configuration option path'),
                new InputOption('format', null, InputOption::VALUE_REQUIRED, sprintf('The output format ("%s")', implode('", "', $this->getAvailableFormatOptions())), 'yaml'),
            ])
            ->setHelp(<<<EOF
The <info>%command.name%</info> command dumps the default configuration for an
extension/bundle.

Either the extension alias or bundle name can be used:

  <info>php %command.full_name% framework</info>
  <info>php %command.full_name% FrameworkBundle</info>

The <info>--format</info> option specifies the format of the configuration,
these are "{$helpFormats}".

  <info>php %command.full_name% FrameworkBundle --format=xml</info>

For dumping a specific option, add its path as second argument (only available for the yaml format):

  <info>php %command.full_name% framework http_client.default_options</info>

EOF
            )
        ;
    }

    /**
     * @throws \LogicException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $errorIo = $io->getErrorStyle();

        if (null === $name = $input->getArgument('name')) {
            $this->listBundles($errorIo);
            $this->listNonBundleExtensions($errorIo);

            $errorIo->comment([
                'Provide the name of a bundle as the first argument of this command to dump its default configuration. (e.g. <comment>config:dump-reference FrameworkBundle</comment>)',
                'For dumping a specific option, add its path as the second argument of this command. (e.g. <comment>config:dump-reference FrameworkBundle http_client.default_options</comment> to dump the <comment>framework.http_client.default_options</comment> configuration)',
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
                throw new InvalidArgumentException(sprintf('Supported formats are "%s".', implode('", "', $this->getAvailableFormatOptions())));
        }

        $io->writeln(null === $path ? $dumper->dump($configuration, $extension->getNamespace()) : $dumper->dumpAtPath($configuration, $path));

        return 0;
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('name')) {
            $suggestions->suggestValues($this->getAvailableExtensions());
            $suggestions->suggestValues($this->getAvailableBundles());
        }

        if ($input->mustSuggestOptionValuesFor('format')) {
            $suggestions->suggestValues($this->getAvailableFormatOptions());
        }
    }

    private function getAvailableExtensions(): array
    {
        $kernel = $this->getApplication()->getKernel();

        $extensions = [];
        foreach ($this->getContainerBuilder($kernel)->getExtensions() as $alias => $extension) {
            $extensions[] = $alias;
        }

        return $extensions;
    }

    private function getAvailableBundles(): array
    {
        $bundles = [];

        foreach ($this->getApplication()->getKernel()->getBundles() as $bundle) {
            $bundles[] = $bundle->getName();
        }

        return $bundles;
    }

    private function getAvailableFormatOptions(): array
    {
        return ['yaml', 'xml'];
    }
}
