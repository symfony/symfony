<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\DependencyInjection\Compiler;

use Symfony\Component\Config\Definition\ArrayShapeGenerator;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\AppReference;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\Routing\Loader\Configurator\RoutesReference;

/**
 * @internal
 */
class PhpConfigReferenceDumpPass implements CompilerPassInterface
{
    private const REFERENCE_TEMPLATE = <<<'PHP'
        <?php

        // This file is auto-generated and is for apps only. Bundles SHOULD NOT rely on its content.

        namespace Symfony\Component\DependencyInjection\Loader\Configurator;

        {APP_TYPES}
        final class App extends AppReference
        {
            {APP_PARAM}
            public static function config(array $config): array
            {
                return parent::config($config);
            }
        }

        namespace Symfony\Component\Routing\Loader\Configurator;

        {ROUTES_TYPES}
        final class Routes extends RoutesReference
        {
            {ROUTES_PARAM}
            public static function config(array $config): array
            {
                return parent::config($config);
            }
        }

        PHP;

    private const WHEN_ENV_APP_TEMPLATE = <<<'PHPDOC'

         *     "when@{ENV}"?: array{
         *         imports?: ImportsConfig,
         *         parameters?: ParametersConfig,
         *         services?: ServicesConfig,{SHAPE}
         *     },
        PHPDOC;

    private const ROUTES_TYPES_TEMPLATE = <<<'PHPDOC'

         * @psalm-type RoutesConfig = array{{SHAPE}
         *     ...<string, RouteConfig|ImportConfig|AliasConfig>
         * }
         */
        PHPDOC;

    private const WHEN_ENV_ROUTES_TEMPLATE = <<<'PHPDOC'

         *     "when@{ENV}"?: array<string, RouteConfig|ImportConfig|AliasConfig>,
        PHPDOC;

    public function __construct(
        private string $referenceFile,
        private array $bundlesDefinition,
    ) {
    }

    public function process(ContainerBuilder $container): void
    {
        $knownEnvs = $container->hasParameter('.container.known_envs') ? $container->getParameter('.container.known_envs') : [$container->getParameter('kernel.environment')];
        $knownEnvs = array_unique($knownEnvs);
        sort($knownEnvs);
        $extensionsPerEnv = [];
        $appTypes = '';

        $anyEnvExtensions = [];
        foreach ($this->bundlesDefinition as $bundle => $envs) {
            if (!is_subclass_of($bundle, BundleInterface::class)) {
                continue;
            }
            if (!$extension = (new $bundle())->getContainerExtension()) {
                continue;
            }
            if (!$configuration = $this->getConfiguration($extension, $container)) {
                continue;
            }
            $anyEnvExtensions[$bundle] = $extension;
            $type = $this->camelCase($extension->getAlias()).'Config';
            $appTypes .= \sprintf("\n * @psalm-type %s = %s", $type, ArrayShapeGenerator::generate($configuration->getConfigTreeBuilder()->buildTree()));

            foreach ($knownEnvs as $env) {
                if ($envs[$env] ?? $envs['all'] ?? false) {
                    $extensionsPerEnv[$env][] = $extension;
                } else {
                    unset($anyEnvExtensions[$bundle]);
                }
            }
        }
        krsort($extensionsPerEnv);

        $r = new \ReflectionClass(AppReference::class);

        if (false === $i = strpos($phpdoc = $r->getDocComment(), "\n * @psalm-type ConfigType = ")) {
            throw new \LogicException(\sprintf('Cannot insert config shape in "%s".', AppReference::class));
        }
        $appTypes = substr_replace($phpdoc, $appTypes, $i, 0);

        if (false === $i = strrpos($phpdoc = $appTypes, "\n *     ...<string, ExtensionType|array{")) {
            throw new \LogicException(\sprintf('Cannot insert config shape in "%s".', AppReference::class));
        }
        $appTypes = substr_replace($phpdoc, $this->getShapeForExtensions($anyEnvExtensions, $container), $i, 0);
        $i += \strlen($appTypes) - \strlen($phpdoc);

        foreach ($extensionsPerEnv as $env => $extensions) {
            $appTypes = substr_replace($appTypes, strtr(self::WHEN_ENV_APP_TEMPLATE, [
                '{ENV}' => $env,
                '{SHAPE}' => $this->getShapeForExtensions($extensions, $container, '    '),
            ]), $i, 0);
        }
        $appParam = $r->getMethod('config')->getDocComment();

        $r = new \ReflectionClass(RoutesReference::class);

        if (false === $i = strpos($phpdoc = $r->getDocComment(), "\n * @psalm-type RoutesConfig = ")) {
            throw new \LogicException(\sprintf('Cannot insert config shape in "%s".', RoutesReference::class));
        }
        $routesTypes = '';
        foreach ($knownEnvs as $env) {
            $routesTypes .= strtr(self::WHEN_ENV_ROUTES_TEMPLATE, ['{ENV}' => $env]);
        }
        if ('' !== $routesTypes) {
            $routesTypes = strtr(self::ROUTES_TYPES_TEMPLATE, ['{SHAPE}' => $routesTypes]);
            $routesTypes = substr_replace($phpdoc, $routesTypes, $i);
        }

        $configReference = strtr(self::REFERENCE_TEMPLATE, [
            '{APP_TYPES}' => $appTypes,
            '{APP_PARAM}' => $appParam,
            '{ROUTES_TYPES}' => $routesTypes,
            '{ROUTES_PARAM}' => $r->getMethod('config')->getDocComment(),
        ]);

        $dir = \dirname($this->referenceFile);
        if (is_dir($dir) && is_writable($dir)) {
            if (!is_file($this->referenceFile) || file_get_contents($this->referenceFile) !== $configReference) {
                file_put_contents($this->referenceFile, $configReference);
            }
            $container->addResource(new FileResource($this->referenceFile));
        }
    }

    private function camelCase(string $input): string
    {
        $output = ucfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $input))));

        return preg_replace('#\W#', '', $output);
    }

    private function getConfiguration(ExtensionInterface $extension, ContainerBuilder $container): ?ConfigurationInterface
    {
        return match (true) {
            $extension instanceof ConfigurationInterface => $extension,
            $extension instanceof ConfigurationExtensionInterface => $extension->getConfiguration([], $container),
            default => null,
        };
    }

    private function getShapeForExtensions(array $extensions, ContainerBuilder $container, string $indent = ''): string
    {
        $shape = '';
        foreach ($extensions as $extension) {
            if ($this->getConfiguration($extension, $container)) {
                $type = $this->camelCase($extension->getAlias()).'Config';
                $shape .= \sprintf("\n *     %s%s?: %s,", $indent, $extension->getAlias(), $type);
            }
        }

        return $shape;
    }
}
