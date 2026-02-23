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
use Symfony\Component\FeatureFlag\Provider\ProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use Symfony\Component\VarDumper\Cloner\Data;

/**
 * @experimental
 */
final class FeatureFlagDataCollector extends DataCollector implements LateDataCollectorInterface
{
    public function __construct(
        private readonly ProviderInterface $provider,
        private readonly TraceableFeatureChecker $featureChecker,
    ) {
        $this->reset();
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
    }

    public function lateCollect(): void
    {
        $this->data['features'] = [];
        foreach ($this->featureChecker->getResolvedValues() as $featureName => $info) {
            $status = $this->provider->get($featureName) ? $info['status'] : TraceableFeatureChecker::STATUS_NOT_FOUND;
            $this->data['features'][$featureName] = [
                'status' => $status,
                'value' => $this->cloneVar($info['value']),
                'calls' => $info['calls'],
            ];
            ++$this->data['count'][$status];
        }
    }

    /**
     * @return array<string, array{status: TraceableFeatureChecker::STATUS_*, value: Data, calls: int}>
     */
    public function getFeatures(): array
    {
        return $this->data['features'];
    }

    public function getEnabledCount(): int
    {
        return $this->data['count'][TraceableFeatureChecker::STATUS_ENABLED];
    }

    public function getDisabledCount(): int
    {
        return $this->data['count'][TraceableFeatureChecker::STATUS_DISABLED];
    }

    public function getResolvedCount(): int
    {
        return $this->data['count'][TraceableFeatureChecker::STATUS_RESOLVED];
    }

    public function getNotFoundCount(): int
    {
        return $this->data['count'][TraceableFeatureChecker::STATUS_NOT_FOUND];
    }

    public function getName(): string
    {
        return 'feature_flag';
    }

    public function reset(): void
    {
        $this->data = [
            'features' => [],
            'count' => [
                TraceableFeatureChecker::STATUS_ENABLED => 0,
                TraceableFeatureChecker::STATUS_DISABLED => 0,
                TraceableFeatureChecker::STATUS_RESOLVED => 0,
                TraceableFeatureChecker::STATUS_NOT_FOUND => 0,
            ],
        ];
    }
}
