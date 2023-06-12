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
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Exception\LogicException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Compiler\ValidateEnvPlaceholdersPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * A console command for dumping available configuration reference.
 *
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 *
 * @final
 */
#[AsCommand(name: 'debug:config', description: 'Dump the current configuration for an extension')]
class ConfigDebugCommand extends AbstractConfigCommand
{
    protected function configure(): void
    {
        $commentedHelpFormats = array_map(static fn (string $format): string => sprintf('<comment>%s</comment>', $format), $this->getAvailableFormatOptions());
        $helpFormats = implode('", "', $commentedHelpFormats);

        $this
            ->setDefinition([
                new InputArgument('name', InputArgument::OPTIONAL, 'The bundle name or the extension alias'),
                new InputArgument('path', InputArgument::OPTIONAL, 'The configuration option path'),
                new InputOption('resolve-env', null, InputOption::VALUE_NONE, 'Display resolved environment variable values instead of placeholders'),
                new InputOption('format', null, InputOption::VALUE_REQUIRED, sprintf('The output format ("%s")', implode('", "', $this->getAvailableFormatOptions())), class_exists(Yaml::class) ? 'txt' : 'json'),
            ])
            ->setHelp(<<<EOF
The <info>%command.name%</info> command dumps the current configuration for an
extension/bundle.

Either the extension alias or bundle name can be used:

  <info>php %command.full_name% framework</info>
  <info>php %command.full_name% FrameworkBundle</info>

The <info>--format</info> option specifies the format of the configuration,
these are "{$helpFormats}".

  <info>php %command.full_name% framework --format=json</info>

For dumping a specific option, add its path as second argument:

  <info>php %command.full_name% framework serializer.enabled</info>

EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $errorIo = $io->getErrorStyle();

        if (null === $name = $input->getArgument('name')) {
            $this->listBundles($errorIo);
            $this->listNonBundleExtensions($errorIo);

            $errorIo->comment('Provide the name of a bundle as the first argument of this command to dump its configuration. (e.g. <comment>debug:config FrameworkBundle</comment>)');
            $errorIo->comment('For dumping a specific option, add its path as the second argument of this command. (e.g. <comment>debug:config FrameworkBundle serializer</comment> to dump the <comment>framework.serializer</comment> configuration)');

            return 0;
        }

        $extension = $this->findExtension($name);
        $extensionAlias = $extension->getAlias();
        $container = $this->compileContainer();

        $config = $this->getConfig($extension, $container, $input->getOption('resolve-env'));

        $format = $input->getOption('format');

        if (\in_array($format, ['txt', 'yml'], true) && !class_exists(Yaml::class)) {
            $errorIo->error('Setting the "format" option to "txt" or "yaml" requires the Symfony Yaml component. Try running "composer install symfony/yaml" or use "--format=json" instead.');

            return 1;
        }

        if (null === $path = $input->getArgument('path')) {
            if ('txt' === $input->getOption('format')) {
                $io->title(
                    sprintf('Current configuration for %s', $name === $extensionAlias ? sprintf('extension with alias "%s"', $extensionAlias) : sprintf('"%s"', $name))
                );
            }

            $io->writeln($this->convertToFormat([$extensionAlias => $config], $format));

            return 0;
        }

        try {
            $config = $this->getConfigForPath($config, $path, $extensionAlias);
        } catch (LogicException $e) {
            $errorIo->error($e->getMessage());

            return 1;
        }

        $io->title(sprintf('Current configuration for "%s.%s"', $extensionAlias, $path));

        $io->writeln($this->convertToFormat($config, $format));

        return 0;
    }

    private function convertToFormat(mixed $config, string $format): string
    {
        return match ($format) {
            'txt', 'yaml' => Yaml::dump($config, 10),
            'json' => json_encode($config, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE),
            default => throw new InvalidArgumentException(sprintf('Supported formats are "%s".', implode('", "', $this->getAvailableFormatOptions()))),
        };
    }

    private function compileContainer(): ContainerBuilder
    {
        $kernel = clone $this->getApplication()->getKernel();
        $kernel->boot();

        $method = new \ReflectionMethod($kernel, 'buildContainer');
        $container = $method->invoke($kernel);
        $container->getCompiler()->compile($container);

        return $container;
    }

    /**
     * Iterate over configuration until the last step of the given path.
     *
     * @throws LogicException If the configuration does not exist
     */
    private function getConfigForPath(array $config, string $path, string $alias): mixed
    {
        $steps = explode('.', $path);

        foreach ($steps as $step) {
            if (!\array_key_exists($step, $config)) {
                throw new LogicException(sprintf('Unable to find configuration for "%s.%s".', $alias, $path));
            }

            $config = $config[$step];
        }

        return $config;
    }

    private function getConfigForExtension(ExtensionInterface $extension, ContainerBuilder $container): array
    {
        $extensionAlias = $extension->getAlias();

        $extensionConfig = [];
        foreach ($container->getCompilerPassConfig()->getPasses() as $pass) {
            if ($pass instanceof ValidateEnvPlaceholdersPass) {
                $extensionConfig = $pass->getExtensionConfig();
                break;
            }
        }

        if (isset($extensionConfig[$extensionAlias])) {
            return $extensionConfig[$extensionAlias];
        }

        // Fall back to default config if the extension has one

        if (!$extension instanceof ConfigurationExtensionInterface && !$extension instanceof ConfigurationInterface) {
            throw new \LogicException(sprintf('The extension with alias "%s" does not have configuration.', $extensionAlias));
        }

        $configs = $container->getExtensionConfig($extensionAlias);
        $configuration = $extension instanceof ConfigurationInterface ? $extension : $extension->getConfiguration($configs, $container);
        $this->validateConfiguration($extension, $configuration);

        return (new Processor())->processConfiguration($configuration, $configs);
    }

    public function complete(CompletionInput $input, CompletionSuggestions $suggestions): void
    {
        if ($input->mustSuggestArgumentValuesFor('name')) {
            $suggestions->suggestValues($this->getAvailableExtensions());
            $suggestions->suggestValues($this->getAvailableBundles());

            return;
        }

        if ($input->mustSuggestArgumentValuesFor('path') && null !== $name = $input->getArgument('name')) {
            try {
                $config = $this->getConfig($this->findExtension($name), $this->compileContainer());
                $paths = array_keys(self::buildPathsCompletion($config));
                $suggestions->suggestValues($paths);
            } catch (LogicException) {
            }
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
        $availableBundles = [];
        foreach ($this->getApplication()->getKernel()->getBundles() as $bundle) {
            $availableBundles[] = $bundle->getName();
        }

        return $availableBundles;
    }

    private function getConfig(ExtensionInterface $extension, ContainerBuilder $container, bool $resolveEnvs = false): mixed
    {
        return $container->resolveEnvPlaceholders(
            $container->getParameterBag()->resolveValue(
                $this->getConfigForExtension($extension, $container)
            ), $resolveEnvs ?: null
        );
    }

    private static function buildPathsCompletion(array $paths, string $prefix = ''): array
    {
        $completionPaths = [];
        foreach ($paths as $key => $values) {
            if (\is_array($values)) {
                $completionPaths = $completionPaths + self::buildPathsCompletion($values, $prefix.$key.'.');
            } else {
                $completionPaths[$prefix.$key] = null;
            }
        }

        return $completionPaths;
    }

    private function getAvailableFormatOptions(): array
    {
        return ['txt', 'yaml', 'json'];
    }
}
