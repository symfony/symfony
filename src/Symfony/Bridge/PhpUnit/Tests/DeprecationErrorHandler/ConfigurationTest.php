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
use Symfony\Bridge\PhpUnit\DeprecationErrorHandler\DeprecationGroup;

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
        $this->assertTrue($configuration->tolerates($this->buildGroups([
            'unsilenced' => 1,
            'self' => 0,
            'legacy' => 1,
            'other' => 0,
            'direct' => 1,
            'indirect' => 1,
        ])));
        $this->assertFalse($configuration->tolerates($this->buildGroups([
            'unsilenced' => 1,
            'self' => 1,
            'legacy' => 1,
            'other' => 0,
            'direct' => 1,
            'indirect' => 1,
        ])));
    }

    public function testItNoticesExceededSelfThreshold()
    {
        $configuration = Configuration::fromUrlEncodedString('max[self]=1');
        $this->assertTrue($configuration->tolerates($this->buildGroups([
            'unsilenced' => 1234,
            'self' => 1,
            'legacy' => 23,
            'other' => 13,
            'direct' => 124,
            'indirect' => 3244,
        ])));
        $this->assertFalse($configuration->tolerates($this->buildGroups([
            'unsilenced' => 1234,
            'self' => 2,
            'legacy' => 23,
            'other' => 13,
            'direct' => 124,
            'indirect' => 3244,
        ])));
    }

    public function testItNoticesExceededDirectThreshold()
    {
        $configuration = Configuration::fromUrlEncodedString('max[direct]=1&max[self]=999999');
        $this->assertTrue($configuration->tolerates($this->buildGroups([
            'unsilenced' => 1234,
            'self' => 123,
            'legacy' => 23,
            'other' => 13,
            'direct' => 1,
            'indirect' => 3244,
        ])));
        $this->assertFalse($configuration->tolerates($this->buildGroups([
            'unsilenced' => 1234,
            'self' => 124,
            'legacy' => 23,
            'other' => 13,
            'direct' => 2,
            'indirect' => 3244,
        ])));
    }

    public function testItNoticesExceededIndirectThreshold()
    {
        $configuration = Configuration::fromUrlEncodedString('max[indirect]=1&max[direct]=999999&max[self]=999999');
        $this->assertTrue($configuration->tolerates($this->buildGroups([
            'unsilenced' => 1234,
            'self' => 123,
            'legacy' => 23,
            'other' => 13,
            'direct' => 1234,
            'indirect' => 1,
        ])));
        $this->assertFalse($configuration->tolerates($this->buildGroups([
            'unsilenced' => 1234,
            'self' => 124,
            'legacy' => 23,
            'other' => 13,
            'direct' => 2324,
            'indirect' => 2,
        ])));
    }

    public function testIndirectThresholdIsUsedAsADefaultForDirectAndSelfThreshold()
    {
        $configuration = Configuration::fromUrlEncodedString('max[indirect]=1');
        $this->assertTrue($configuration->tolerates($this->buildGroups([
            'unsilenced' => 0,
            'self' => 1,
            'legacy' => 0,
            'other' => 0,
            'direct' => 0,
            'indirect' => 0,
        ])));
        $this->assertFalse($configuration->tolerates($this->buildGroups([
            'unsilenced' => 0,
            'self' => 2,
            'legacy' => 0,
            'other' => 0,
            'direct' => 0,
            'indirect' => 0,
        ])));
        $this->assertTrue($configuration->tolerates($this->buildGroups([
            'unsilenced' => 0,
            'self' => 0,
            'legacy' => 0,
            'other' => 0,
            'direct' => 1,
            'indirect' => 0,
        ])));
        $this->assertFalse($configuration->tolerates($this->buildGroups([
            'unsilenced' => 0,
            'self' => 0,
            'legacy' => 0,
            'other' => 0,
            'direct' => 2,
            'indirect' => 0,
        ])));
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
        $this->assertFalse($configuration->verboseOutput('unsilenced'));
        $this->assertFalse($configuration->verboseOutput('direct'));
        $this->assertFalse($configuration->verboseOutput('indirect'));
        $this->assertFalse($configuration->verboseOutput('self'));
        $this->assertFalse($configuration->verboseOutput('other'));
    }

    public function testItCanBePartiallyShushed()
    {
        $configuration = Configuration::fromUrlEncodedString('quiet[]=unsilenced&quiet[]=indirect&quiet[]=other');
        $this->assertFalse($configuration->verboseOutput('unsilenced'));
        $this->assertTrue($configuration->verboseOutput('direct'));
        $this->assertFalse($configuration->verboseOutput('indirect'));
        $this->assertTrue($configuration->verboseOutput('self'));
        $this->assertFalse($configuration->verboseOutput('other'));
    }

    public function testItThrowsOnUnknownVerbosityGroup()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('made-up');
        Configuration::fromUrlEncodedString('quiet[]=made-up');
    }

    public function testOutputIsNotVerboseInWeakMode()
    {
        $configuration = Configuration::inWeakMode();
        $this->assertFalse($configuration->verboseOutput('unsilenced'));
        $this->assertFalse($configuration->verboseOutput('direct'));
        $this->assertFalse($configuration->verboseOutput('indirect'));
        $this->assertFalse($configuration->verboseOutput('self'));
        $this->assertFalse($configuration->verboseOutput('other'));
    }

    private function buildGroups($counts)
    {
        $groups = [];
        foreach ($counts as $name => $count) {
            $groups[$name] = new DeprecationGroup();
            $i = 0;
            while ($i++ < $count) {
                $groups[$name]->addNotice();
            }
        }

        return $groups;
    }
}
