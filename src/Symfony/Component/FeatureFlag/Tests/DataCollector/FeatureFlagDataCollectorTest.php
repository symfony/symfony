<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\FeatureFlag\Tests\DataCollector;

use PHPUnit\Framework\TestCase;
use Symfony\Component\FeatureFlag\DataCollector\FeatureFlagDataCollector;
use Symfony\Component\FeatureFlag\Debug\TraceableFeatureChecker;
use Symfony\Component\FeatureFlag\FeatureChecker;
use Symfony\Component\FeatureFlag\FeatureRegistry;

class FeatureFlagDataCollectorTest extends TestCase
{
    public function testLateCollect()
    {
        $featureRegistry = new FeatureRegistry([
            'feature_true' => fn () => true,
            'feature_integer' => fn () => 42,
            'feature_random' => fn () => random_int(1, 42),
        ]);
        $traceableFeatureChecker = new TraceableFeatureChecker(new FeatureChecker($featureRegistry));
        $dataCollector = new FeatureFlagDataCollector($featureRegistry, $traceableFeatureChecker);

        $traceableFeatureChecker->isEnabled('feature_true');
        $traceableFeatureChecker->isEnabled('feature_integer', 1);

        $this->assertSame([], $dataCollector->getChecks());

        $dataCollector->lateCollect();

        $data = array_map(fn ($v) => $v->getValue(), $dataCollector->getResolvedValues());
        $this->assertSame(
            [
                'feature_true' => true,
                'feature_integer' => 42,
            ],
            $data,
        );

        $data = array_map(
            fn ($checks) => array_map(function ($a) {
                $a['expected_value'] = $a['expected_value']->getValue();

                return $a;
            }, $checks),
            $dataCollector->getChecks(),
        );
        $this->assertSame(
            [
                'feature_true' => [
                    [
                        'expected_value' => true,
                        'is_enabled' => true,
                        'calls' => 1,
                    ],
                ],
                'feature_integer' => [
                    [
                        'expected_value' => 1,
                        'is_enabled' => false,
                        'calls' => 1,
                    ],
                ],
            ],
            $data,
        );

        $this->assertSame(['feature_random'], $dataCollector->getNotResolved());
    }
}
