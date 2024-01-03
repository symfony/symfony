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
use Symfony\Component\FeatureFlag\FeatureRegistry;

class TraceableFeatureCheckerTest extends TestCase
{
    public function testTraces()
    {
        $featureChecker = new FeatureChecker(new FeatureRegistry([
            'feature_true' => fn () => true,
            'feature_integer' => fn () => 42,
            'feature_random' => fn () => random_int(1, 42),
        ]));
        $traceableFeatureChecker = new TraceableFeatureChecker($featureChecker);

        $this->assertTrue($traceableFeatureChecker->isEnabled('feature_true'));
        $this->assertFalse($traceableFeatureChecker->isEnabled('feature_integer', 1));
        $this->assertFalse($traceableFeatureChecker->isEnabled('unknown_feature'));

        $this->assertSame(
            [
                'feature_true' => true,
                'feature_integer' => false,
                'unknown_feature' => false,

            ],
            $traceableFeatureChecker->getChecks(),
        );
        $this->assertSame(
            [
                'feature_true' => true,
                'feature_integer' => 42,
                'unknown_feature' => false,
            ],
            $traceableFeatureChecker->getValues(),
        );
    }
}
