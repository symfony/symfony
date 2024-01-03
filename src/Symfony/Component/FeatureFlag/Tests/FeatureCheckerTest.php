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
use Symfony\Component\FeatureFlag\FeatureRegistry;

class FeatureCheckerTest extends TestCase
{
    public function testGetValue()
    {
        $featureChecker = new FeatureChecker(new FeatureRegistry([
            'feature_integer' => fn () => 42,
            'feature_random' => fn () => random_int(1, 42),
        ]));

        $this->assertSame(42, $featureChecker->getValue('feature_integer'));
        $this->assertIsInt($value = $featureChecker->getValue('feature_random'));
        $this->assertSame($value, $featureChecker->getValue('feature_random'));
    }

    public function testGetDefaultValue()
    {
        $featureRegistry = new FeatureRegistry([
            'existing_feature' => fn () => 1,
        ]);

        $this->assertSame(1, (new FeatureChecker($featureRegistry))->getValue('existing_feature'));
        $this->assertSame(1, (new FeatureChecker($featureRegistry, 42))->getValue('existing_feature'));

        $this->assertFalse((new FeatureChecker($featureRegistry))->getValue('unknown_feature'));
        $this->assertSame(42, (new FeatureChecker($featureRegistry, 42))->getValue('unknown_feature'));
    }

    public function testIsEnabled()
    {
        $featureChecker = new FeatureChecker(new FeatureRegistry([
            'feature_true' => fn () => true,
            'feature_false' => fn () => false,
            'feature_integer' => fn () => 1,
        ]));

        $this->assertTrue($featureChecker->isEnabled('feature_true'));
        $this->assertFalse($featureChecker->isEnabled('feature_false'));
        $this->assertFalse($featureChecker->isEnabled('feature_integer'));
        $this->assertFalse($featureChecker->isEnabled('unknown_feature'));

        $this->assertFalse($featureChecker->isDisabled('feature_true'));
        $this->assertTrue($featureChecker->isDisabled('feature_false'));
        $this->assertTrue($featureChecker->isDisabled('feature_integer'));
        $this->assertTrue($featureChecker->isDisabled('unknown_feature'));
    }

    /**
     * @dataProvider provideIsEnabledWithExpectedValue
     */
    public function testIsEnabledWithExpectedValue(string $featureName, mixed $expectedFeatureValue, bool $expectedResult)
    {
        $featureChecker = new FeatureChecker(new FeatureRegistry([
            'feature_true' => fn () => true,
            'feature_integer' => fn () => 1,
        ]));

        $this->assertSame($expectedResult, $featureChecker->isEnabled($featureName, $expectedFeatureValue));
    }

    public static function provideIsEnabledWithExpectedValue()
    {
        yield 'with the same boolean' => ['feature_true', true, true];
        yield 'with the same integer' => ['feature_integer', 1, true];
        yield 'with a different boolean' => ['feature_true', false, false];
        yield 'with different types' => ['feature_integer', true, false];
    }
}
