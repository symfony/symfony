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

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;

/**
 * Merges extension configs into the container builder.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
class MergeExtensionConfigurationPass implements CompilerPassInterface
{
    /**
     * {@inheritdoc}
     */
    public function process(ContainerBuilder $container)
    {
        $parameters = $container->getParameterBag()->all();
        $definitions = $container->getDefinitions();
        $aliases = $container->getAliases();
        $exprLangProviders = $container->getExpressionLanguageProviders();

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
            // EnvPlaceholderParameterBag tracks env vars when calling resolveValue().
            // Clone so that tracking is done in a dedicated bag.
            $resolvingBag = clone $container->getParameterBag();
            $config = $resolvingBag->resolveValue($config);

            $tmpContainer = new ContainerBuilder($container->getParameterBag());
            $tmpContainer->setResourceTracking($container->isTrackingResources());
            $tmpContainer->addObjectResource($extension);
            if ($extension instanceof ConfigurationExtensionInterface && null !== $configuration = $extension->getConfiguration($config, $tmpContainer)) {
                $tmpContainer->addObjectResource($configuration);
            }

            foreach ($exprLangProviders as $provider) {
                $tmpContainer->addExpressionLanguageProvider($provider);
            }

            $extension->load($config, $tmpContainer);

            if ($resolvingBag instanceof EnvPlaceholderParameterBag) {
                // $resolvingBag keeps track of env vars encoutered *before* merging configs
                if ($extension instanceof Extension) {
                    // but we don't want to keep track of env vars that are *overridden* when configs are merged
                    $resolvingBag = new MergeExtensionConfigurationParameterBag($extension, $resolvingBag);
                }
                $container->getParameterBag()->mergeEnvPlaceholders($resolvingBag);
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
    private $beforeProcessingEnvPlaceholders;

    public function __construct(Extension $extension, parent $resolvingBag)
    {
        $this->beforeProcessingEnvPlaceholders = $resolvingBag->getEnvPlaceholders();
        $config = $this->resolveEnvPlaceholders($extension->getProcessedConfigs());
        parent::__construct($this->resolveValue($config));
    }

    /**
     * {@inheritdoc}
     */
    public function get($name)
    {
        return $this->has($name) || (0 === strpos($name, 'env(') && ')' === substr($name, -1) && 'env()' !== $name) ? parent::get($name) : '';
    }

    /**
     * {@inheritdoc}
     */
    public function getEnvPlaceholders()
    {
        // contains the list of env vars that are still used after configs have been merged
        $envPlaceholders = parent::getEnvPlaceholders();

        foreach ($envPlaceholders as $env => $placeholders) {
            if (isset($this->beforeProcessingEnvPlaceholders[$env])) {
                // for still-used env vars, keep track of their before-processing placeholders
                $envPlaceholders[$env] += $this->beforeProcessingEnvPlaceholders[$env];
            }
        }

        return $envPlaceholders;
    }

    /**
     * Replaces-back env placeholders to their original "%env(FOO)%" version.
     */
    private function resolveEnvPlaceholders($value)
    {
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                $value[$this->resolveEnvPlaceholders($k)] = $this->resolveEnvPlaceholders($v);
            }
        } elseif (is_string($value)) {
            foreach ($this->beforeProcessingEnvPlaceholders as $env => $placeholders) {
                foreach ($placeholders as $placeholder) {
                    if (false !== stripos($value, $placeholder)) {
                        $value = str_ireplace($placeholder, "%env($env)%", $value);
                    }
                }
            }
        }

        return $value;
    }
}
