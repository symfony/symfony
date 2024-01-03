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

        $this->assertSame([], $dataCollector->getFeatures());

        $dataCollector->lateCollect();

        $data = array_map(fn ($a) => array_merge($a, ['value' => $a['value']->getValue()]), $dataCollector->getFeatures());
        $this->assertSame(
            [
                'feature_true' => [
                    'is_enabled' => true,
                    'value' => true,
                ],
                'feature_integer' => [
                    'is_enabled' => false,
                    'value' => 42,
                ],
                'feature_random' => [
                    'is_enabled' => null,
                    'value' => null,
                ],
            ],
            $data,
        );
    }
}
