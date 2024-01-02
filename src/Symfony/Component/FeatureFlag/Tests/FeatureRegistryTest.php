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
use Symfony\Component\FeatureFlag\FeatureRegistry;

class FeatureRegistryTest extends TestCase
{
    private FeatureRegistry $featureRegistry;

    protected function setUp(): void
    {
        $this->featureRegistry = new FeatureRegistry([
            'first_feature' => fn () => true,
            'second_feature' => fn () => false,
        ]);
    }

    public function testGet()
    {
        $this->assertIsCallable($this->featureRegistry->get('first_feature'));
    }

    public function testGetNotFound()
    {
        $this->expectException(FeatureNotFoundException::class);
        $this->expectExceptionMessage('Feature "unknown_feature" not found.');

        $this->featureRegistry->get('unknown_feature');
    }

    public function testGetNames()
    {
        $this->assertSame(['first_feature', 'second_feature'], $this->featureRegistry->getNames());
    }
}
