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
use Symfony\Component\FeatureFlag\Provider\InMemoryProvider;

class FeatureFlagDataCollectorTest extends TestCase
{
    public function testLateCollect()
    {
        $featureRegistry = new InMemoryProvider([
            'feature_true' => static fn () => true,
            'feature_false' => static fn () => false,
            'feature_integer' => static fn () => 42,
            'feature_random' => static fn () => random_int(1, 42),
        ]);
        $traceableFeatureChecker = new TraceableFeatureChecker(new FeatureChecker($featureRegistry));
        $dataCollector = new FeatureFlagDataCollector($featureRegistry, $traceableFeatureChecker);

        $traceableFeatureChecker->isEnabled('feature_true');
        $traceableFeatureChecker->isEnabled('feature_false');
        $traceableFeatureChecker->isEnabled('feature_unknown');
        $traceableFeatureChecker->getValue('feature_integer');
        $traceableFeatureChecker->getValue('feature_integer');

        $this->assertSame([], $dataCollector->getResolved());

        $dataCollector->lateCollect();

        $data = array_map(
            static function (array $a): array {
                $a['value'] = $a['value']->getValue();

                return $a;
            },
            $dataCollector->getResolved(),
        );
        $this->assertSame(
            [
                'feature_true' => [
                    'status' => 'enabled',
                    'value' => true,
                    'calls' => 1,
                ],
                'feature_false' => [
                    'status' => 'disabled',
                    'value' => false,
                    'calls' => 1,
                ],
                'feature_unknown' => [
                    'status' => 'not_found',
                    'value' => false,
                    'calls' => 1,
                ],
                'feature_integer' => [
                    'status' => 'resolved',
                    'value' => 42,
                    'calls' => 2,
                ],
            ],
            $data,
        );

        $this->assertSame(['feature_random'], $dataCollector->getNotResolved());
    }
}
