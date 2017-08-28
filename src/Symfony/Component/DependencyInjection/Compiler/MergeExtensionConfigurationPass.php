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
            $resolvingBag = $container->getParameterBag();
            if ($resolvingBag instanceof EnvPlaceholderParameterBag && $extension instanceof Extension) {
                // create a dedicated bag so that we can track env vars per-extension
                $resolvingBag = new MergeExtensionConfigurationParameterBag($resolvingBag);
            }
            $config = $resolvingBag->resolveValue($config);

            $tmpContainer = new ContainerBuilder($resolvingBag);
            $tmpContainer->setResourceTracking($container->isTrackingResources());
            $tmpContainer->addObjectResource($extension);
            if ($extension instanceof ConfigurationExtensionInterface && null !== $configuration = $extension->getConfiguration($config, $tmpContainer)) {
                $tmpContainer->addObjectResource($configuration);
            }

            foreach ($exprLangProviders as $provider) {
                $tmpContainer->addExpressionLanguageProvider($provider);
            }

            $extension->load($config, $tmpContainer);

            if ($resolvingBag instanceof MergeExtensionConfigurationParameterBag) {
                // don't keep track of env vars that are *overridden* when configs are merged
                $resolvingBag->freezeAfterProcessing($extension);
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
    private $processedEnvPlaceholders;

    public function __construct(parent $parameterBag)
    {
        parent::__construct($parameterBag->all());
        $this->mergeEnvPlaceholders($parameterBag);
    }

    public function freezeAfterProcessing(Extension $extension)
    {
        $this->processedEnvPlaceholders = array();

        // serialize config to catch env vars nested in object graphs
        $config = serialize($extension->getProcessedConfigs());

        foreach (parent::getEnvPlaceholders() as $env => $placeholders) {
            foreach ($placeholders as $placeholder) {
                if (false !== stripos($config, $placeholder)) {
                    $this->processedEnvPlaceholders[$env] = $placeholders;
                    break;
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getEnvPlaceholders()
    {
        return null !== $this->processedEnvPlaceholders ? $this->processedEnvPlaceholders : parent::getEnvPlaceholders();
    }
}
