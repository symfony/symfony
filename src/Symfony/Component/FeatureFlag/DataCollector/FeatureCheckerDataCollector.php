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

use Symfony\Component\FeatureFlag\FeatureRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;

final class FeatureCheckerDataCollector extends DataCollector implements LateDataCollectorInterface
{
    private array $isEnabledLogs = [];
    private array $valueLogs = [];

    public function __construct(
        private readonly FeatureRegistry $featureRegistry,
    ) {
    }

    public function collect(Request $request, Response $response, \Throwable $exception = null): void
    {
    }

    public function collectIsEnabled(string $featureName, bool $result): void
    {
        $this->isEnabledLogs[$featureName] = $result;
    }

    public function collectValue(string $featureName, mixed $value): void
    {
        $this->valueLogs[$featureName] = $value;
    }

    public function getName(): string
    {
        return 'feature_flag';
    }

    public function reset(): void
    {
        parent::reset();

        $this->isEnabledLogs = [];
        $this->valueLogs = [];
    }

    public function lateCollect(): void
    {
        $this->data['features'] = [];
        foreach ($this->featureRegistry->getNames() as $featureName) {
            $this->data['features'][$featureName] = [
                'is_enabled' => $this->isEnabledLogs[$featureName] ?? null,
                'value' => $this->cloneVar($this->valueLogs[$featureName] ?? null),
            ];
        }
    }

    public function getFeatures(): array
    {
        return $this->data['features'];
    }
}
