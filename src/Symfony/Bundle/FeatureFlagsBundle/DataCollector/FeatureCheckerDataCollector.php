<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FeatureFlagsBundle\DataCollector;

use Symfony\Component\FeatureFlags\StrategyResult;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use Symfony\Component\VarDumper\Caster\ClassStub;
use Symfony\Component\VarDumper\Cloner\Data;

/**
 * @property Data|array{
 *     checks: array<string, array{
 *         feature: string,
 *         result: bool|null,
 *         computes: array<string, array{
 *             strategyId: string,
 *             strategyClass: string,
 *             level: int,
 *             result: StrategyResult|null,
 *         }>,
 *     }>,
 * } $data
 */
final class FeatureCheckerDataCollector extends DataCollector implements LateDataCollectorInterface
{
    /** @var \SplStack<string> */
    private \SplStack $currentCheck;

    /** @var \SplStack<string> */
    private \SplStack $currentCompute;

    public function __construct()
    {
        $this->data = ['checks' => []];
        $this->currentCheck = new \SplStack();
        $this->currentCompute = new \SplStack();
    }

    public function collect(Request $request, Response $response, \Throwable $exception = null): void
    {
    }

    public function collectIsEnabledStart(string $featureName): void
    {
        $checkId = uniqid();

        $this->data['checks'][$checkId] = [
            'feature' => $featureName,
            'computes' => [],
            'result' => null,
        ];
        $this->currentCheck->push($checkId);
    }

    /**
     * @param class-string $strategyClass
     */
    public function collectComputeStart(string $strategyId, string $strategyClass): void
    {
        $checkId = $this->currentCheck->top();
        $computeId = uniqid();
        $level = $this->currentCompute->count();

        $this->data['checks'][$checkId]['computes'][$computeId] = [
            'strategyId' => $strategyId,
            'strategyClass' => new ClassStub($strategyClass),
            'level' => $level,
            'result' => null,
        ];
        $this->currentCompute->push($computeId);
    }

    public function collectComputeStop(StrategyResult $result): void
    {
        $checkId = $this->currentCheck->top();
        $computeId = $this->currentCompute->pop();

        $this->data['checks'][$checkId]['computes'][$computeId]['result'] = $result;
    }

    public function collectIsEnabledStop(bool $result): void
    {
        $checkId = $this->currentCheck->pop();

        $this->data['checks'][$checkId]['result'] = $result;
    }

    public function getName(): string
    {
        return 'feature_flags';
    }

    public function reset(): void
    {
        $this->data = [
            'checks' => [],
        ];
    }

    public function lateCollect(): void
    {
        $this->data = $this->cloneVar($this->data);
    }

    public function getChecks(): array|Data
    {
        return $this->data['checks'];
    }
}
