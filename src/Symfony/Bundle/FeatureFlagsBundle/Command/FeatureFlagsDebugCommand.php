<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FeatureFlagsBundle\Command;

use Symfony\Bundle\FeatureFlagsBundle\Debug\TraceableStrategy;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\FeatureFlags\Feature;
use Symfony\Component\FeatureFlags\Provider\ProviderInterface;
use Symfony\Component\FeatureFlags\Strategy\OuterStrategiesInterface;
use Symfony\Component\FeatureFlags\Strategy\OuterStrategyInterface;
use Symfony\Component\FeatureFlags\Strategy\StrategyInterface;

/**
 * A console command for retrieving information about feature flags.
 */
#[AsCommand(name: 'debug:feature-flags', description: 'Display configured features and their provider for an application')]
final class FeatureFlagsDebugCommand extends Command
{
    /** @var iterable<string, ProviderInterface> */
    private iterable $featureProviders;

    /** @param iterable<string, ProviderInterface> $featureProviders */
    public function __construct(iterable $featureProviders)
    {
        parent::__construct();

        $this->featureProviders = $featureProviders;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('featureName', InputArgument::OPTIONAL, 'Feature name. If provided will display the full tree of strategies regarding that feature.')
            ->setHelp(<<<'EOF'
The <info>%command.name%</info> command displays all configured feature flags:

  <info>php %command.full_name%</info>

To get more insight for a flag, specify its name:

  <info>php %command.full_name% my-feature</info>
EOF
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (null === $input->getArgument('featureName')) {
            return $this->listAllFeaturesPerProvider($io);
        }

        return $this->detailsFeature($io, $input->getArgument('featureName'));
    }

    private function detailsFeature(SymfonyStyle $io, string $debuggingFeatureName): int
    {
        $io->title("About \"{$debuggingFeatureName}\" flag");

        $providerNames = [];
        $featureFoundName = null;
        $featureFoundProviders = [];
        $candidates = [];

        foreach ($this->featureProviders as $serviceName => $featureProvider) {
            $providerName = $featureProvider::class;
            if ($providerName !== $serviceName) {
                $providerName .= " ({$serviceName})";
            }
            $providerNames[] = $providerName;

            foreach ($featureProvider->names() as $featureName) {
                if (
                    !\in_array($featureName, $candidates, true)
                    && (str_contains($featureName, $debuggingFeatureName) || \levenshtein($featureName, $debuggingFeatureName) <= \strlen($featureName) / 3)
                ) {
                    $candidates[] = $featureName;
                }

                if ($featureName !== $debuggingFeatureName) {
                    continue;
                }

                $featureFoundName = $featureName;
                $featureFoundProviders[] = $providerName;

                if (\count($featureFoundProviders) > 1) {
                    continue;
                }

                $feature = $featureProvider->get($featureName);
                $featureGetDefault = \Closure::bind(fn (): bool => $feature->default, $feature, Feature::class);

                $io
                    ->createTable()
                    ->setHorizontal()
                    ->setHeaders(['Name', 'Description', 'Default', 'Provider', 'Strategy Tree'])
                    ->addRow([
                        $featureName,
                        \chunk_split($feature->getDescription(), 40, "\n"),
                        \json_encode($featureGetDefault()),
                        $providerName,
                        $this->getStrategyTreeFromFeature($feature),
                    ])
                    ->setStyle('compact')
                    ->render()
                ;
            }
        }

        if (null !== $featureFoundName) {
            $this->renderDuplicateWarnings($io, $featureFoundName, $featureFoundProviders);

            return 0;
        }

        $warning = \sprintf(
            "\"%s\" not found in any of the following providers :\n%s",
            $debuggingFeatureName,
            \implode("\n", \array_map(fn (string $providerName) => '  * '.$providerName, $providerNames)),
        );
        if (0 < \count($candidates)) {
            $warning .= \sprintf(
                "\n\nDid you mean \"%s\"?",
                \implode('", "', $candidates),
            );
        }
        $io->warning($warning);

        return 1;
    }

    private function listAllFeaturesPerProvider(SymfonyStyle $io): int
    {
        $io->title('Feature list grouped by their providers');

        $order = 0;
        $groupedFeatureProviders = [];

        foreach ($this->featureProviders as $serviceName => $featureProvider) {
            ++$order;

            $providerName = $featureProvider::class;
            if ($providerName !== $serviceName) {
                $providerName .= " ({$serviceName}).";
            }
            $io->section("#{$order} - {$providerName}");

            $tableHeaders = ['Name', 'Description', 'Default', 'Main Strategy'];
            $tableRows = [];

            foreach ($featureProvider->names() as $featureName) {
                $groupedFeatureProviders[$featureName] ??= [];
                $groupedFeatureProviders[$featureName][] = $providerName;

                $feature = $featureProvider->get($featureName);

                $featureGetDefault = \Closure::bind(fn (): bool => $feature->default, $feature, Feature::class);
                $featureGetStrategy = \Closure::bind(fn (): StrategyInterface => $feature->strategy, $feature, Feature::class);

                $strategy = $featureGetStrategy();
                $strategyClass = $strategy::class;
                $strategyId = null;

                if ($strategy instanceof TraceableStrategy) {
                    $strategyGetId = \Closure::bind(fn (): string => $strategy->strategyId, $strategy, TraceableStrategy::class);

                    $strategyId = $strategyGetId();
                    $strategyClass = $strategy->getInnerStrategy()::class;
                }

                $strategyString = $strategyClass;
                if (null !== $strategyId) {
                    $strategyString .= " ({$strategyId})";
                }

                $rowFeatureName = $featureName;

                if (\count($groupedFeatureProviders[$featureName]) > 1) {
                    $rowFeatureName .= ' (⚠️ duplicated)';
                }

                $tableRows[] = [
                    $rowFeatureName,
                    \chunk_split($feature->getDescription(), 40, "\n"),
                    \json_encode($featureGetDefault()),
                    $strategyString,
                ];
            }
            $io->table($tableHeaders, $tableRows);
        }

        foreach ($groupedFeatureProviders as $featureName => $featureProviders) {
            $this->renderDuplicateWarnings($io, $featureName, $featureProviders);
        }

        return 0;
    }

    private function getStrategyTreeFromFeature(Feature $feature): string
    {
        $featureGetStrategy = \Closure::bind(fn (): StrategyInterface => $feature->strategy, $feature, Feature::class);

        $strategyTree = $this->getStrategyTree($featureGetStrategy());

        return $this->convertStrategyTreeToString($strategyTree);
    }

    private function getStrategyTree(StrategyInterface $strategy, string|null $strategyId = null): array
    {
        $children = [];

        if ($strategy instanceof TraceableStrategy) {
            $strategyGetId = \Closure::bind(fn (): string => $strategy->strategyId, $strategy, TraceableStrategy::class);

            return $this->getStrategyTree($strategy->getInnerStrategy(), $strategyGetId());
        } elseif ($strategy instanceof OuterStrategiesInterface) {
            $children = \array_map(
                fn (StrategyInterface $strategyInterface): array => $this->getStrategyTree($strategyInterface),
                $strategy->getInnerStrategies()
            );
        } elseif ($strategy instanceof OuterStrategyInterface) {
            $children = [$this->getStrategyTree($strategy->getInnerStrategy())];
        }

        return [
            'id' => $strategyId,
            'class' => $strategy::class,
            'children' => $children,
        ];
    }

    private function convertStrategyTreeToString(array $strategyTree, int $indent = 0): string
    {
        $childIndicator = 'L ';
        $spaces = \str_repeat(' ', $indent * \strlen($childIndicator));

        $prefix = '' === $spaces ? '' : "{$spaces}{$childIndicator}";

        $row = $strategyTree['class'];

        if (null !== $strategyTree['id']) {
            $row .= " ({$strategyTree['id']})";
        }

        $row .= "\n";

        foreach ($strategyTree['children'] as $child) {
            $row .= $this->convertStrategyTreeToString($child, $indent + 1);
        }

        return "{$prefix}{$row}";
    }

    /**
     * @param list<string> $providerNames
     */
    private function renderDuplicateWarnings(SymfonyStyle $io, string $featureName, array $providerNames): void
    {
        $duplicatesCount = \count($providerNames) - 1;
        if (0 === $duplicatesCount) {
            return;
        }

        $providerNames = \array_slice($providerNames, -$duplicatesCount);

        if (1 === $duplicatesCount) {
            $warningMessage = \sprintf('Found 1 duplicate for "%s" feature, which will probably never be used, in those providers:', $featureName);
        } else {
            $warningMessage = \sprintf('Found %d duplicates for "%s" feature, which will probably never be used, in those providers:', $duplicatesCount, $featureName);
        }

        $warningMessage .= "\n";
        $warningMessage .= \implode("\n", \array_map(fn (string $providerName): string => '  * '.$providerName, $providerNames));

        $io->warning($warningMessage);
    }
}
