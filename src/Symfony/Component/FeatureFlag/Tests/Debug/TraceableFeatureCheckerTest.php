<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\FeatureFlag\Tests\Debug;

use PHPUnit\Framework\TestCase;
use Symfony\Component\FeatureFlag\Debug\TraceableFeatureChecker;
use Symfony\Component\FeatureFlag\FeatureChecker;
use Symfony\Component\FeatureFlag\Provider\InMemoryProvider;

class TraceableFeatureCheckerTest extends TestCase
{
    public function testTraces()
    {
        $featureChecker = new FeatureChecker(new InMemoryProvider([
            'feature_true' => fn () => true,
            'feature_integer' => fn () => 42,
            'feature_random' => fn () => random_int(1, 42),
        ]));
        $traceableFeatureChecker = new TraceableFeatureChecker($featureChecker);

        $this->assertTrue($traceableFeatureChecker->isEnabled('feature_true'));
        $this->assertFalse($traceableFeatureChecker->isEnabled('feature_integer', 1));

        $this->assertSame(
            [
                'feature_true' => [['expectedValue' => true, 'isEnabled' => true, 'calls' => 1]],
                'feature_integer' => [['expectedValue' => 1, 'isEnabled' => false, 'calls' => 1]],
            ],
            $traceableFeatureChecker->getChecks(),
        );
        $this->assertSame(
            [
                'feature_true' => true,
                'feature_integer' => 42,
            ],
            $traceableFeatureChecker->getResolvedValues(),
        );
    }
}
