<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\DependencyInjection\Compiler;

use Symfony\Component\Config\Definition\BaseNode;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Exception\RuntimeException;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Merges extension configs into the container builder.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class MergeExtensionConfigurationPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $parameters = $container->getParameterBag()->all();
        $definitions = $container->getDefinitions();
        $aliases = $container->getAliases();
        $exprLangProviders = $container->getExpressionLanguageProviders();
        $configAvailable = class_exists(BaseNode::class);

        foreach ($container->getExtensions() as $extension) {
            if ($extension instanceof PrependExtensionInterface) {
                $extension->prepend($container);
            }
        }

        foreach ($container->getExtensions() as $name => $extension) {
            if (!$config = $container->getExtensionConfig($name)) {
                // this extension was not called
                continue;
            }
            $resolvingBag = $container->getParameterBag();
            if ($resolvingBag instanceof EnvPlaceholderParameterBag && $extension instanceof Extension) {
                // create a dedicated bag so that we can track env vars per-extension
                $resolvingBag = new MergeExtensionConfigurationParameterBag($resolvingBag);
                if ($configAvailable) {
                    BaseNode::setPlaceholderUniquePrefix($resolvingBag->getEnvPlaceholderUniquePrefix());
                }
            }
            $config = $resolvingBag->resolveValue($config);

            try {
                $tmpContainer = new MergeExtensionConfigurationContainerBuilder($extension, $resolvingBag);
                $tmpContainer->setResourceTracking($container->isTrackingResources());
                $tmpContainer->addObjectResource($extension);
                if ($extension instanceof ConfigurationExtensionInterface && null !== $configuration = $extension->getConfiguration($config, $tmpContainer)) {
                    $tmpContainer->addObjectResource($configuration);
                }

                foreach ($exprLangProviders as $provider) {
                    $tmpContainer->addExpressionLanguageProvider($provider);
                }

                $extension->load($config, $tmpContainer);
            } catch (\Exception $e) {
                if ($resolvingBag instanceof MergeExtensionConfigurationParameterBag) {
                    $container->getParameterBag()->mergeEnvPlaceholders($resolvingBag);
                }

                throw $e;
            }

            if ($resolvingBag instanceof MergeExtensionConfigurationParameterBag) {
                // don't keep track of env vars that are *overridden* when configs are merged
                $resolvingBag->freezeAfterProcessing($extension, $tmpContainer);
            }

            $container->merge($tmpContainer);
            $container->getParameterBag()->add($parameters);
        }

        $container->addDefinitions($definitions);
        $container->addAliases($aliases);
    }
}

/**
 * @internal
 */
class MergeExtensionConfigurationParameterBag extends EnvPlaceholderParameterBag
{
    private array $processedEnvPlaceholders;

    public function __construct(parent $parameterBag)
    {
        parent::__construct($parameterBag->all());
        $this->mergeEnvPlaceholders($parameterBag);
    }

    public function freezeAfterProcessing(Extension $extension, ContainerBuilder $container): void
    {
        if (!$config = $extension->getProcessedConfigs()) {
            // Extension::processConfiguration() wasn't called, we cannot know how configs were merged
            return;
        }
        $this->processedEnvPlaceholders = [];

        // serialize config and container to catch env vars nested in object graphs
        $config = serialize($config).serialize($container->getDefinitions()).serialize($container->getAliases()).serialize($container->getParameterBag()->all());

        if (false === stripos($config, 'env_')) {
            return;
        }

        preg_match_all('/env_[a-f0-9]{16}_\w+_[a-f0-9]{32}/Ui', $config, $matches);
        $usedPlaceholders = array_flip($matches[0]);
        foreach (parent::getEnvPlaceholders() as $env => $placeholders) {
            foreach ($placeholders as $placeholder) {
                if (isset($usedPlaceholders[$placeholder])) {
                    $this->processedEnvPlaceholders[$env] = $placeholders;
                    break;
                }
            }
        }
    }

    public function getEnvPlaceholders(): array
    {
        return $this->processedEnvPlaceholders ?? parent::getEnvPlaceholders();
    }

    public function getUnusedEnvPlaceholders(): array
    {
        return !isset($this->processedEnvPlaceholders) ? [] : array_diff_key(parent::getEnvPlaceholders(), $this->processedEnvPlaceholders);
    }
}

/**
 * A container builder preventing using methods that wouldn't have any effect from extensions.
 *
 * @internal
 */
class MergeExtensionConfigurationContainerBuilder extends ContainerBuilder
{
    private string $extensionClass;

    public function __construct(ExtensionInterface $extension, ?ParameterBagInterface $parameterBag = null)
    {
        parent::__construct($parameterBag);

        $this->extensionClass = $extension::class;
    }

    public function addCompilerPass(CompilerPassInterface $pass, string $type = PassConfig::TYPE_BEFORE_OPTIMIZATION, int $priority = 0): static
    {
        throw new LogicException(sprintf('You cannot add compiler pass "%s" from extension "%s". Compiler passes must be registered before the container is compiled.', get_debug_type($pass), $this->extensionClass));
    }

    public function registerExtension(ExtensionInterface $extension): void
    {
        throw new LogicException(sprintf('You cannot register extension "%s" from "%s". Extensions must be registered before the container is compiled.', get_debug_type($extension), $this->extensionClass));
    }

    public function compile(bool $resolveEnvPlaceholders = false): void
    {
        throw new LogicException(sprintf('Cannot compile the container in extension "%s".', $this->extensionClass));
    }

    public function resolveEnvPlaceholders(mixed $value, string|bool|null $format = null, ?array &$usedEnvs = null): mixed
    {
        if (true !== $format || !\is_string($value)) {
            return parent::resolveEnvPlaceholders($value, $format, $usedEnvs);
        }

        $bag = $this->getParameterBag();
        $value = $bag->resolveValue($value);

        if (!$bag instanceof EnvPlaceholderParameterBag) {
            return parent::resolveEnvPlaceholders($value, $format, $usedEnvs);
        }

        foreach ($bag->getEnvPlaceholders() as $env => $placeholders) {
            if (!str_contains($env, ':')) {
                continue;
            }
            foreach ($placeholders as $placeholder) {
                if (false !== stripos($value, $placeholder)) {
                    throw new RuntimeException(sprintf('Using a cast in "env(%s)" is incompatible with resolution at compile time in "%s". The logic in the extension should be moved to a compiler pass, or an env parameter with no cast should be used instead.', $env, $this->extensionClass));
                }
            }
        }

        return parent::resolveEnvPlaceholders($value, $format, $usedEnvs);
    }
}
