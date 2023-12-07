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
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
    }

    public function lateCollect(): void
    {
        $this->data['resolved'] = [];
        foreach ($this->featureChecker->getResolvedValues() as $featureName => $info) {
            $this->data['resolved'][$featureName] = [
                'status' => $this->provider->get($featureName) ? $info['status'] : 'not_found',
                'value' => $this->cloneVar($info['value']),
                'calls' => $info['calls'],
            ];
        }

        $this->data['not_resolved'] = array_values(array_diff($this->provider->getNames(), array_keys($this->data['resolved'])));
    }

    /**
     * @return array<string, array{status: 'not_found'|'resolved'|'enabled'|'disabled', value: Data, calls: int}>
     */
    public function getResolved(): array
    {
        return $this->data['resolved'] ?? [];
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
