<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bridge\PhpUnit\Tests\DeprecationErrorHandler;

use PHPUnit\Framework\TestCase;
use Symfony\Bridge\PhpUnit\DeprecationErrorHandler\Configuration;

class ConfigurationTest extends TestCase
{
    public function testItThrowsOnStringishValue()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('hi');
        Configuration::fromUrlEncodedString('hi');
    }

    public function testItThrowsOnUnknownConfigurationOption()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('min');
        Configuration::fromUrlEncodedString('min[total]=42');
    }

    public function testItThrowsOnUnknownThreshold()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('deep');
        Configuration::fromUrlEncodedString('max[deep]=42');
    }

    public function testItThrowsOnStringishThreshold()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('forty-two');
        Configuration::fromUrlEncodedString('max[total]=forty-two');
    }

    public function testItNoticesExceededTotalThreshold()
    {
        $configuration = Configuration::fromUrlEncodedString('max[total]=3');
        $this->assertTrue($configuration->tolerates([
            'unsilencedCount' => 1,
            'remaining selfCount' => 0,
            'legacyCount' => 1,
            'otherCount' => 0,
            'remaining directCount' => 1,
            'remaining indirectCount' => 1,
        ]));
        $this->assertFalse($configuration->tolerates([
            'unsilencedCount' => 1,
            'remaining selfCount' => 1,
            'legacyCount' => 1,
            'otherCount' => 0,
            'remaining directCount' => 1,
            'remaining indirectCount' => 1,
        ]));
    }

    public function testItNoticesExceededSelfThreshold()
    {
        $configuration = Configuration::fromUrlEncodedString('max[self]=1');
        $this->assertTrue($configuration->tolerates([
            'unsilencedCount' => 1234,
            'remaining selfCount' => 1,
            'legacyCount' => 23,
            'otherCount' => 13,
            'remaining directCount' => 124,
            'remaining indirectCount' => 3244,
        ]));
        $this->assertFalse($configuration->tolerates([
            'unsilencedCount' => 1234,
            'remaining selfCount' => 2,
            'legacyCount' => 23,
            'otherCount' => 13,
            'remaining directCount' => 124,
            'remaining indirectCount' => 3244,
        ]));
    }

    public function testItNoticesExceededDirectThreshold()
    {
        $configuration = Configuration::fromUrlEncodedString('max[direct]=1&max[self]=999999');
        $this->assertTrue($configuration->tolerates([
            'unsilencedCount' => 1234,
            'remaining selfCount' => 123,
            'legacyCount' => 23,
            'otherCount' => 13,
            'remaining directCount' => 1,
            'remaining indirectCount' => 3244,
        ]));
        $this->assertFalse($configuration->tolerates([
            'unsilencedCount' => 1234,
            'remaining selfCount' => 124,
            'legacyCount' => 23,
            'otherCount' => 13,
            'remaining directCount' => 2,
            'remaining indirectCount' => 3244,
        ]));
    }

    public function testItNoticesExceededIndirectThreshold()
    {
        $configuration = Configuration::fromUrlEncodedString('max[indirect]=1&max[direct]=999999&max[self]=999999');
        $this->assertTrue($configuration->tolerates([
            'unsilencedCount' => 1234,
            'remaining selfCount' => 123,
            'legacyCount' => 23,
            'otherCount' => 13,
            'remaining directCount' => 1234,
            'remaining indirectCount' => 1,
        ]));
        $this->assertFalse($configuration->tolerates([
            'unsilencedCount' => 1234,
            'remaining selfCount' => 124,
            'legacyCount' => 23,
            'otherCount' => 13,
            'remaining directCount' => 2324,
            'remaining indirectCount' => 2,
        ]));
    }

    public function testIndirectThresholdIsUsedAsADefaultForDirectAndSelfThreshold()
    {
        $configuration = Configuration::fromUrlEncodedString('max[indirect]=1');
        $this->assertTrue($configuration->tolerates([
            'unsilencedCount' => 0,
            'remaining selfCount' => 1,
            'legacyCount' => 0,
            'otherCount' => 0,
            'remaining directCount' => 0,
            'remaining indirectCount' => 0,
        ]));
        $this->assertFalse($configuration->tolerates([
            'unsilencedCount' => 0,
            'remaining selfCount' => 2,
            'legacyCount' => 0,
            'otherCount' => 0,
            'remaining directCount' => 0,
            'remaining indirectCount' => 0,
        ]));
        $this->assertTrue($configuration->tolerates([
            'unsilencedCount' => 0,
            'remaining selfCount' => 0,
            'legacyCount' => 0,
            'otherCount' => 0,
            'remaining directCount' => 1,
            'remaining indirectCount' => 0,
        ]));
        $this->assertFalse($configuration->tolerates([
            'unsilencedCount' => 0,
            'remaining selfCount' => 0,
            'legacyCount' => 0,
            'otherCount' => 0,
            'remaining directCount' => 2,
            'remaining indirectCount' => 0,
        ]));
    }

    public function testItCanTellWhetherToDisplayAStackTrace()
    {
        $configuration = Configuration::fromUrlEncodedString('');
        $this->assertFalse($configuration->shouldDisplayStackTrace('interesting'));

        $configuration = Configuration::fromRegex('/^interesting/');
        $this->assertFalse($configuration->shouldDisplayStackTrace('uninteresting'));
        $this->assertTrue($configuration->shouldDisplayStackTrace('interesting'));
    }

    public function testItCanBeDisabled()
    {
        $configuration = Configuration::fromUrlEncodedString('disabled');
        $this->assertFalse($configuration->isEnabled());
    }

    public function testItCanBeShushed()
    {
        $configuration = Configuration::fromUrlEncodedString('verbose');
        $this->assertFalse($configuration->verboseOutput());
    }

    public function testOutputIsNotVerboseInWeakMode()
    {
        $configuration = Configuration::inWeakMode();
        $this->assertFalse($configuration->verboseOutput());
    }
}
