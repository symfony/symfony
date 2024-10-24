<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\FrameworkBundle\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\FeatureFlag\ClassFeature;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\FeatureFlag\ClassMethodFeature;
use Symfony\Bundle\FrameworkBundle\Tests\Fixtures\FeatureFlag\NamedFeature;
use Symfony\Component\FeatureFlag\FeatureCheckerInterface;

class FeatureFlagTest extends AbstractWebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        self::deleteTmpDir();
    }

    public function testFeatureFlagAssertions()
    {
        static::bootKernel(['test_case' => 'FeatureFlag', 'root_config' => 'config.yml']);
        /** @var FeatureCheckerInterface $featureChecker */
        $featureChecker = static::getContainer()->get('feature_flag.feature_checker');

        // With default behavior
        $this->assertTrue($featureChecker->isEnabled(ClassFeature::class));
        $this->assertTrue($featureChecker->isEnabled(ClassMethodFeature::class));

        // With a custom name
        $this->assertTrue($featureChecker->isEnabled('custom_name'));
        $this->assertFalse($featureChecker->isEnabled(NamedFeature::class));

        // With an unknown feature
        $this->assertFalse($featureChecker->isEnabled('unknown'));

        // Get values
        $this->assertSame('green', $featureChecker->getValue('method_string'));
        $this->assertSame(42, $featureChecker->getValue('method_int'));
    }

    public function testFeatureFlagAssertionsWithInvalidMethod()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid feature method "Symfony\Bundle\FrameworkBundle\Tests\Fixtures\FeatureFlag\InvalidMethodFeature": method "Symfony\Bundle\FrameworkBundle\Tests\Fixtures\FeatureFlag\InvalidMethodFeature::invalid_method()" does not exist.');

        static::bootKernel(['test_case' => 'FeatureFlag', 'root_config' => 'config_with_invalid_method.yml']);
    }

    public function testFeatureFlagAssertionsWithDifferentMethod()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Using the #[Symfony\Component\FeatureFlag\Attribute\AsFeature(method: "different")] attribute on a method is not valid. Either remove the method value or move this to the top of the class (Symfony\Bundle\FrameworkBundle\Tests\Fixtures\FeatureFlag\DifferentMethodFeature).');

        static::bootKernel(['test_case' => 'FeatureFlag', 'root_config' => 'config_with_different_method.yml']);
    }

    public function testFeatureFlagAssertionsWithDuplicate()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Feature "Symfony\Bundle\FrameworkBundle\Tests\Fixtures\FeatureFlag\ClassFeature" already defined.');

        static::bootKernel(['test_case' => 'FeatureFlag', 'root_config' => 'config_with_duplicate.yml']);
    }
}
