<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Debug\Section;

use Psr\Container\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Debug\DebugItem;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Compiler\ValidateEnvPlaceholdersPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * The "Config" tab of the interactive "debug" command.
 *
 * Reuses the same data sources and rendering as the "debug:config" and
 * "debug:dotenv" commands. Search results are split into two groups: the
 * bundle/extension configuration and the dotenv environment variables. The
 * detail pane dumps the current configuration of the selected extension, or the
 * value of the selected variable.
 *
 * @author Javier Eguiluz <javier.eguiluz@gmail.com>
 *
 * @experimental
 */
final class ConfigDebugSection extends AbstractDebugSection
{
    private const string GROUP_CONFIG = 'Config';
    private const string GROUP_ENV = 'Env Var';

    private ?ContainerBuilder $container = null;

    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly ?ContainerInterface $envVarProcessors = null,
        private readonly string $kernelEnvironment = 'dev',
        private readonly string $projectDir = '',
    ) {
    }

    public function getLabel(): string
    {
        return 'Config';
    }

    public function getShortLabel(): string
    {
        return 'Config';
    }

    public function describe(DebugItem $item, int $width): string
    {
        return $item->detail ?? $this->describeToString($item->type, $item->value);
    }

    private function describeToString(string $type, string $value): string
    {
        $buffer = new BufferedOutput(OutputInterface::VERBOSITY_NORMAL, true);
        $io = new SymfonyStyle(new ArrayInput([]), $buffer);

        try {
            if ('extension' === $type) {
                $this->describeExtension($io, $value);
            } else {
                $this->describeDotenvVariable($io, $value);
            }
        } catch (\Throwable $e) {
            return $this->formatError($value, $e);
        }

        return $buffer->fetch();
    }

    private function formatError(string $value, \Throwable $e): string
    {
        return \sprintf("\e[91mError while loading \"%s\"\e[39m\n\n%s", $value, $e->getMessage());
    }

    protected function buildItems(): array
    {
        $items = [];

        $aliasToBundle = [];
        foreach ($this->kernel->getBundles() as $bundle) {
            if ($extension = $bundle->getContainerExtension()) {
                $aliasToBundle[$extension->getAlias()] = $bundle->getName();
            }
        }

        $aliases = array_keys($this->getContainer()->getExtensions());
        sort($aliases);
        foreach ($aliases as $alias) {
            $detail = $this->describeToString('extension', $alias);
            $items[] = new DebugItem('extension', $alias, $alias, self::GROUP_CONFIG, $detail, searchText: implode("\n", array_filter([$aliasToBundle[$alias] ?? '', $detail])));
        }

        $vars = array_keys($this->getDotenvVariables());
        sort($vars);
        foreach ($vars as $var) {
            $items[] = new DebugItem('dotenv', $var, $var, self::GROUP_ENV);
        }

        return $items;
    }

    private function describeExtension(SymfonyStyle $io, string $alias): void
    {
        $container = $this->getContainer();

        if (!$container->hasExtension($alias)) {
            throw new \LogicException(\sprintf('No extension with alias "%s" is enabled.', $alias));
        }

        $config = $this->getConfig($container->getExtension($alias), $container);

        $io->title(\sprintf('Current configuration for "%s"', $alias));
        $io->writeln($this->dump([$alias => $config]));
    }

    private function describeDotenvVariable(SymfonyStyle $io, string $var): void
    {
        $io->title($var);
        $io->definitionList(['Value' => (string) ($_SERVER[$var] ?? $_ENV[$var] ?? '')]);

        $perFile = [];
        foreach ($this->getAvailableEnvFiles() as $file) {
            $values = $this->loadValues($file);
            if (\array_key_exists($var, $values)) {
                $perFile[] = \sprintf('%s: %s', $this->getRelativeName($file), $values[$var]);
            }
        }

        if ($perFile) {
            $io->section('Defined in (descending priority)');
            $io->listing($perFile);
        }

        $io->comment('Note that values might be different between web and CLI.');
    }

    /**
     * Compiles the container once and caches it. Cloning + booting the kernel and
     * compiling the container is expensive, so it must not run on every keystroke.
     *
     * Deliberately mirrors ConfigDebugCommand::compileContainer() instead of using
     * BuildDebugContainerTrait: the trait's cached path restores the dumped container
     * without running registerContainerConfiguration() or the ValidateEnvPlaceholdersPass,
     * so the actual (non-default) extension configuration would not be available.
     */
    private function getContainer(): ContainerBuilder
    {
        if (null !== $this->container) {
            return $this->container;
        }

        $kernel = clone $this->kernel;
        $kernel->boot();

        $method = new \ReflectionMethod($kernel, 'buildContainer');
        $container = $method->invoke($kernel);
        if ($this->envVarProcessors) {
            $container->set('container.env_var_processors_locator', $this->envVarProcessors);
        }
        $container->getCompiler()->compile($container);

        return $this->container = $container;
    }

    private function getConfig(ExtensionInterface $extension, ContainerBuilder $container): mixed
    {
        return $container->resolveEnvPlaceholders(
            $container->getParameterBag()->resolveValue(
                $this->getConfigForExtension($extension, $container)
            ), null
        );
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
        $configs = $container->getExtensionConfig($extensionAlias);

        if ($extension instanceof ConfigurationInterface) {
            $configuration = $extension;
        } elseif ($extension instanceof ConfigurationExtensionInterface) {
            $configuration = $extension->getConfiguration($configs, $container);
        } else {
            throw new \LogicException(\sprintf('The extension with alias "%s" does not have configuration.', $extensionAlias));
        }

        if (!$configuration instanceof ConfigurationInterface) {
            throw new \LogicException(\sprintf('The extension with alias "%s" does not have its getConfiguration() method setup.', $extensionAlias));
        }

        return (new Processor())->processConfiguration($configuration, $configs);
    }

    private function dump(mixed $config): string
    {
        if (class_exists(Yaml::class)) {
            return Yaml::dump($config, 10);
        }

        $dump = json_encode($config, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);

        return false === $dump ? '' : $dump;
    }

    /**
     * @return array<string, mixed>
     */
    private function getDotenvVariables(): array
    {
        $variables = [];
        foreach ($this->getAvailableEnvFiles() as $file) {
            $variables += $this->loadValues($file);
        }

        return $variables;
    }

    /**
     * @return list<string>
     */
    private function getAvailableEnvFiles(): array
    {
        return array_values(array_filter($this->getEnvFiles($this->getDotenvPath()), 'is_file'));
    }

    private function getDotenvPath(): string
    {
        $config = [];
        $projectDir = $this->projectDir;

        if (is_file($projectDir)) {
            $config = ['dotenv_path' => basename($projectDir)];
            $projectDir = \dirname($projectDir);
        }

        $composerFile = $projectDir.'/composer.json';
        $config += $_SERVER['APP_RUNTIME_OPTIONS'] ?? (is_file($composerFile) ? json_decode(file_get_contents($composerFile), true) : [])['extra']['runtime'] ?? [];

        return $projectDir.'/'.($config['dotenv_path'] ?? '.env');
    }

    /**
     * @return list<string>
     */
    private function getEnvFiles(string $filePath): array
    {
        $files = [
            \sprintf('%s.local.php', $filePath),
            \sprintf('%s.%s.local', $filePath, $this->kernelEnvironment),
            \sprintf('%s.%s', $filePath, $this->kernelEnvironment),
        ];

        if ('test' !== $this->kernelEnvironment) {
            $files[] = \sprintf('%s.local', $filePath);
        }

        if (!is_file($filePath) && is_file(\sprintf('%s.dist', $filePath))) {
            $files[] = \sprintf('%s.dist', $filePath);
        } else {
            $files[] = $filePath;
        }

        return $files;
    }

    private function getRelativeName(string $filePath): string
    {
        $projectDir = is_file($this->projectDir) ? \dirname($this->projectDir) : $this->projectDir;

        if (str_starts_with($filePath, $projectDir.'/') || str_starts_with($filePath, $projectDir.\DIRECTORY_SEPARATOR)) {
            return substr($filePath, \strlen($projectDir) + 1);
        }

        return basename($filePath);
    }

    /**
     * @return array<string, string>
     */
    private function loadValues(string $filePath): array
    {
        if (str_ends_with($filePath, '.php')) {
            return include $filePath;
        }

        if (!class_exists(Dotenv::class)) {
            return [];
        }

        return (new Dotenv())->parse(file_get_contents($filePath));
    }
}
