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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\FeatureFlag\FeatureChecker;
use Symfony\Component\FeatureFlag\Provider\InMemoryProvider;

class FeatureCheckerTest extends TestCase
{
    private FeatureChecker $featureChecker;
    private int $counter = 0;

    protected function setUp(): void
    {
        $this->featureChecker = new FeatureChecker(new InMemoryProvider([
            'feature_true' => static fn () => true,
            'feature_false' => static fn () => false,
            'feature_integer' => static fn () => 42,
            'feature_random' => static fn () => random_int(1, 42),
            'feature_counter' => fn () => ++$this->counter,
        ]));
    }

    public function testGetValue()
    {
        $this->assertSame(42, $this->featureChecker->getValue('feature_integer'));
    }

    public function testGetValueCache()
    {
        $this->assertIsInt($value = $this->featureChecker->getValue('feature_random'));
        $this->assertSame($value, $this->featureChecker->getValue('feature_random'));
    }

    public function testGetValueOnNotFound()
    {
        $this->assertFalse($this->featureChecker->getValue('unknown_feature'));
    }

    #[DataProvider('provideIsEnabled')]
    public function testIsEnabled(string $featureName, bool $expectedResult)
    {
        $this->assertSame($expectedResult, $this->featureChecker->isEnabled($featureName));
    }

    public static function provideIsEnabled(): iterable
    {
        yield '"true"' => ['feature_true', true];
        yield '"false"' => ['feature_false', false];
        yield 'an integer' => ['feature_integer', false];
        yield 'an unknown feature' => ['unknown_feature', false];
    }

    public function testReset()
    {
        $this->assertSame(1, $this->featureChecker->getValue('feature_counter'));
        $this->assertSame(1, $this->featureChecker->getValue('feature_counter'));

        $this->featureChecker->reset();

        $this->assertSame(2, $this->featureChecker->getValue('feature_counter'));
    }
}
