<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\FeatureFlag\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\FeatureFlag\FeatureChecker;
use Symfony\Component\FeatureFlag\Provider\InMemoryProvider;

class FeatureCheckerTest extends TestCase
{
    private FeatureChecker $featureChecker;

    protected function setUp(): void
    {
        $this->featureChecker = new FeatureChecker(new InMemoryProvider([
            'feature_true' => fn () => true,
            'feature_false' => fn () => false,
            'feature_integer' => fn () => 42,
            'feature_random' => fn () => random_int(1, 42),
        ]));
    }

    public function testGetValue()
    {
        $this->assertSame(42, $this->featureChecker->getValue('feature_integer'));

        $this->assertIsInt($value = $this->featureChecker->getValue('feature_random'));
        $this->assertSame($value, $this->featureChecker->getValue('feature_random'));
    }

    public function testGetValueOnNotFound()
    {
        $this->assertFalse($this->featureChecker->getValue('unknown_feature'));
    }

    /**
     * @dataProvider provideIsEnabled
     */
    public function testIsEnabled(string $featureName, bool $expectedResult)
    {
        $this->assertSame($expectedResult, $this->featureChecker->isEnabled($featureName));
    }

    public static function provideIsEnabled()
    {
        yield '"true" without expected value' => ['feature_true', true];
        yield '"false" without expected value' => ['feature_false', false];
        yield 'an integer without expected value' => ['feature_integer', false];
        yield 'an unknown feature' => ['unknown_feature', false];
    }

    /**
     * @dataProvider providesEnabledComparedToAnExpectedValue
     */
    public function testIsEnabledComparedToAnExpectedValue(string $featureName, mixed $expectedValue, bool $expectedResult)
    {
        $this->assertSame($expectedResult, $this->featureChecker->isEnabled($featureName, $expectedValue));
    }

    public static function providesEnabledComparedToAnExpectedValue()
    {
        yield '"true" and the same expected value' => ['feature_true', true, true];
        yield '"true" and a different expected value' => ['feature_true', false, false];
        yield '"false" and the same expected value' => ['feature_false', true, false];
        yield '"false" and a different expected value' => ['feature_false', false, true];
        yield 'an integer and the same expected value' => ['feature_integer', 42, true];
        yield 'an integer and a different expected value' => ['feature_integer', 1, false];
    }
}
