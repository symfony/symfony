<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\FeatureFlag\DataCollector;

use Symfony\Component\FeatureFlag\Debug\TraceableFeatureChecker;
use Symfony\Component\FeatureFlag\FeatureRegistryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use Symfony\Component\VarDumper\Cloner\Data;

final class FeatureFlagDataCollector extends DataCollector implements LateDataCollectorInterface
{
    public function __construct(
        private readonly FeatureRegistryInterface $featureRegistry,
        private readonly TraceableFeatureChecker $featureChecker,
    ) {
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
    }

    public function lateCollect(): void
    {
        $this->data['resolvedValues'] = [];
        foreach ($this->featureChecker->getResolvedValues() as $featureName => $resolvedValue) {
            $this->data['resolvedValues'][$featureName] = $this->cloneVar($resolvedValue);
        }

        $this->data['checks'] = [];
        foreach ($this->featureChecker->getChecks() as $featureName => $checks) {
            $this->data['checks'][$featureName] = array_map(
                fn (array $check): array => [
                    'expected_value' => $this->cloneVar($check['expectedValue']),
                    'is_enabled' => $check['isEnabled'],
                    'calls' => $check['calls'],
                ],
                $checks,
            );
        }

        $this->data['not_resolved'] = array_values(array_diff($this->featureRegistry->getNames(), array_keys($this->data['resolvedValues'])));
    }

    /**
     * @return array<string, Data>
     */
    public function getResolvedValues(): array
    {
        return $this->data['resolvedValues'] ?? [];
    }

    /**
     * @return array<string, array{expected_value: Data, is_enabled: bool, calls: int}>
     */
    public function getChecks(): array
    {
        return $this->data['checks'] ?? [];
    }

    /**
     * @return list<string>
     */
    public function getNotResolved(): array
    {
        return $this->data['not_resolved'] ?? [];
    }

    public function getName(): string
    {
        return 'feature_flag';
    }
}
