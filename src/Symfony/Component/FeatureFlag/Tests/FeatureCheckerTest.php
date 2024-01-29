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
use Symfony\Component\FeatureFlag\Exception\FeatureNotFoundException;
use Symfony\Component\FeatureFlag\FeatureChecker;
use Symfony\Component\FeatureFlag\FeatureRegistry;

class FeatureCheckerTest extends TestCase
{
    private FeatureChecker $featureChecker;

    protected function setUp(): void
    {
        $this->featureChecker = new FeatureChecker(new FeatureRegistry([
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
        $this->expectException(FeatureNotFoundException::class);
        $this->expectExceptionMessage('Feature "unknown_feature" not found.');

        $this->featureChecker->getValue('unknown_feature');
    }

    /**
     * @dataProvider provideIsEnabled
     */
    public function testIsEnabled(bool $expectedResult, string $featureName)
    {
        $this->assertSame($expectedResult, $this->featureChecker->isEnabled($featureName));
    }

    public static function provideIsEnabled()
    {
        yield '"true" without expected value' => [true, 'feature_true'];
        yield '"false" without expected value' => [false, 'feature_false'];
        yield 'an integer without expected value' => [false, 'feature_integer'];
        yield 'an unknown feature' => [false, 'unknown_feature'];
    }

    /**
     * @dataProvider providesEnabledComparedToAnExpectedValue
     */
    public function testIsEnabledComparedToAnExpectedValue(bool $expectedResult, string $featureName, mixed $expectedValue)
    {
        $this->assertSame($expectedResult, $this->featureChecker->isEnabled($featureName, $expectedValue));
    }

    public static function providesEnabledComparedToAnExpectedValue()
    {
        yield '"true" and the same expected value' => [true, 'feature_true', true];
        yield '"true" and a different expected value' => [false, 'feature_true', false];
        yield '"false" and the same expected value' => [false, 'feature_false', true];
        yield '"false" and a different expected value' => [true, 'feature_false', false];
        yield 'an integer and the same expected value' => [true, 'feature_integer', 42];
        yield 'an integer and a different expected value' => [false, 'feature_integer', 1];
    }
}
