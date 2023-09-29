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
use Symfony\Component\DependencyInjection\Argument\ServiceClosureArgument;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\FeatureToggle\Feature;
use Symfony\Component\FeatureToggle\Provider\ProviderInterface;
use Symfony\Component\FeatureToggle\Strategy\StrategyInterface;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Routing\Router;
use Twig\Environment;

final class FeatureToggleExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $container->registerForAutoconfiguration(ProviderInterface::class)
            ->addTag('feature_toggle.feature_provider')
        ;

        $config = $this->processConfiguration(new Configuration(), $configs);

        $loader = new PhpFileLoader($container, new FileLocator(\dirname(__DIR__).'/Resources/config'));
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

    private function loadFeatures(ContainerBuilder $container, array $config): void
    {
        $features = [];
        foreach ($config['features'] as $featureName => $featureConfig) {
            $features[$featureName] = new ServiceClosureArgument((new Definition(Feature::class))
                ->setShared(false)
                ->setArguments([
                    $featureName,
                    $featureConfig['description'],
                    $featureConfig['default'],
                    new Reference($featureConfig['strategy']),
                ]))
            ;
        }

        $container->getDefinition('feature_toggle.provider.lazy_in_memory')
            ->setArgument('$features', $features)
        ;
    }

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
        $definition = new ChildDefinition("feature_toggle.abstract_strategy.{$type}");

        return match ($type) {
            'date' => $definition->setArguments([
                '$since' => new Definition(\DateTimeImmutable::class, [$with['since']]),
                '$until' => new Definition(\DateTimeImmutable::class, [$with['until']]),
                '$includeSince' => $with['includeSince'],
                '$includeUntil' => $with['includeUntil'],
            ]),
            'env' => $definition->setArguments(['$envName' => $with['name']]),
            'request_header' => $definition->setArguments(['$headerName' => $with['name']]),
            'request_query' => $definition->setArguments(['$queryParameterName' => $with['name']]),
            'request_attribute' => $definition->setArguments(['$attributeName' => $with['name']]), // Check if RequestStack class exists
            'priority', 'affirmative', 'unanimous' => $definition->setArguments([
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
