<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Asset\Tests\VersionStrategy;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\VersionStrategy\CachedVersionStrategy;
use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class CachedVersionStrategyTest extends TestCase
{
    public function testGetVersion()
    {
        $value = '1.0.0';
        $strategy = $this->getMockBuilder(VersionStrategyInterface::class)->getMock();
        $strategy
            ->expects($this->once())
            ->method('getVersion')
            ->willReturn($value);

        $cache = new ArrayAdapter();
        $cachedVersionStrategy = new CachedVersionStrategy($strategy, $cache);
        $path = 'test-path';

        $this->assertSame($value, $cachedVersionStrategy->getVersion($path));
        $this->assertSame($value, $cachedVersionStrategy->getVersion($path), '2nd call is cached');
    }

    public function testApplyVersion()
    {
        $value = 'test/path/1.0.0';
        $strategy = $this->getMockBuilder(VersionStrategyInterface::class)->getMock();
        $strategy
            ->expects($this->once())
            ->method('applyVersion')
            ->willReturn($value);

        $cache = new ArrayAdapter();
        $cachedVersionStrategy = new CachedVersionStrategy($strategy, $cache);
        $path = 'test/path';

        $this->assertSame($value, $cachedVersionStrategy->applyVersion($path));
        $this->assertSame($value, $cachedVersionStrategy->applyVersion($path), '2nd call is cached');
    }
}
