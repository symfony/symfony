<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\FeatureFlags\Tests\Strategy;

use PHPUnit\Framework\TestCase;
use Symfony\Component\FeatureFlags\Strategy\StrategyInterface;
use Symfony\Component\FeatureFlags\StrategyResult;

abstract class AbstractOuterStrategiesTestCase extends TestCase
{
    protected static function generateStrategy(StrategyResult $strategyResult): StrategyInterface
    {
        return new class($strategyResult) implements StrategyInterface {
            public function __construct(private StrategyResult $result)
            {
            }

            public function compute(): StrategyResult
            {
                return $this->result;
            }
        };
    }
}
