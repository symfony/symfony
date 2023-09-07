<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FeatureToggleBundle\DependencyInjection;

use Symfony\Bundle\FeatureToggleBundle\Strategy\CustomStrategy;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\FeatureToggle\Feature;
use Symfony\Component\FeatureToggle\Strategy\StrategyInterface;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Routing\Router;
use Twig\Environment;

/**
 * @phpstan-import-type ConfigurationType from Configuration
 */
final class FeatureToggleExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        /** @var ConfigurationType $config */
        $config = $this->processConfiguration(new Configuration(), $configs);

        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__) . '/Resources/config'));
        $loader->load('feature.php');
        $loader->load('providers.php');
        $loader->load('strategies.php');

        // Configuration
        $this->loadStrategies($container, $config);
        $this->loadFeatures($container, $config);

        // Third party
        if ($container::willBeAvailable('twig/twig', Environment::class, ['symfony/twig-bundle'])) {
            $loader->load('twig.php');
        }
        if ($container::willBeAvailable('symfony/expression-language', Router::class, ['symfony/framework-bundle', 'symfony/routing'])) {
            $loader->load('routing.php');
        }

        // Debug
        if ($container->getParameter('kernel.debug')) {
            $loader->load('debug.php');
        }
    }

    /**
     * @param ConfigurationType $config
     */
    private function loadFeatures(ContainerBuilder $container, array $config): void
    {
        $features = [];
        foreach ($config['features'] as $featureName => $featureConfig) {
            $definition = new Definition(Feature::class, [
                '$name' => $featureName,
                '$description' => $featureConfig['description'],
                '$default' => $featureConfig['default'],
                '$strategy' => new Reference($featureConfig['strategy']),
            ]);
            $container->setDefinition($featureName, $definition);

            $features[] = new Reference($featureName);
        }

        $container->getDefinition('toggle_feature.provider.in_memory')
            ->setArguments([
                '$features' => $features,
            ])
        ;
    }

    /**
     * @param ConfigurationType $config
     */
    private function loadStrategies(ContainerBuilder $container, array $config): void
    {
        $container->registerForAutoconfiguration(StrategyInterface::class)
            ->addTag('feature_toggle.feature_strategy')
        ;

        foreach ($config['strategies'] as $strategyName => $strategyConfig) {
            $container->setDefinition($strategyName, $this->generateStrategy($strategyConfig['type'], $strategyConfig['with']))
                ->addTag('feature_toggle.feature_strategy');
        }
    }

    /**
     * @param array<string, mixed> $with
     */
    private function generateStrategy(string $type, array $with): Definition
    {
        $definition = new ChildDefinition("toggle_feature.abstract_strategy.{$type}");

        return match ($type) {
            'date' => $definition->setArguments([
                '$from' => new Definition(\DateTimeImmutable::class, [$with['from']]),
                '$until' => new Definition(\DateTimeImmutable::class, [$with['until']]),
                '$includeFrom' => $with['includeFrom'],
                '$includeUntil' => $with['includeUntil'],
            ]),
            'env' => $definition->setArguments(['$envName' => $with['name']]),
            'native_request_header' => $definition->setArguments(['$headerName' => $with['name']]),
            'native_request_query' => $definition->setArguments(['$queryParameterName' => $with['name']]),
            'request_attribute' => $definition->setArguments(['$attributeName' => $with['name']]), // Check if RequestStack class exists
            'priority', 'affirmative' => $definition->setArguments([
                '$strategies' => array_map(
                    static fn (string $referencedStrategyName): Reference => new Reference($referencedStrategyName), // @phpstan-ignore-line
                    (array) $with['strategies'],
                ),
            ]),
            'not' => $definition->setArguments([
                '$inner' => new Reference($with['strategy']), // @phpstan-ignore-line
            ]),
            'grant', 'deny', => $definition,
            default => (new Definition(CustomStrategy::class))->setDecoratedService($type)->setArguments([
                '$inner' => new Reference('.inner'),
            ]),
        };
    }
}
