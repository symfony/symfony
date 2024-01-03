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

final class FeatureFlagDataCollector extends DataCollector implements LateDataCollectorInterface
{
    public function __construct(
        private readonly FeatureRegistryInterface $featureRegistry,
        private readonly TraceableFeatureChecker $featureChecker,
    ) {
        $this->reset();
    }

    public function collect(Request $request, Response $response, \Throwable $exception = null): void
    {
    }

    public function lateCollect(): void
    {
        $checks = $this->featureChecker->getChecks();
        $values = $this->featureChecker->getValues();

        foreach ($this->featureRegistry->getNames() as $featureName) {
            $this->data['features'][$featureName] = [
                'is_enabled' => $checks[$featureName] ?? null,
                'value' => $this->cloneVar($values[$featureName] ?? null),
            ];
        }
    }

    public function getFeatures(): array
    {
        return $this->data['features'];
    }

    public function getName(): string
    {
        return 'feature_flag';
    }

    public function reset(): void
    {
        $this->data = ['features' => []];

        $this->featureChecker->reset();
    }
}
