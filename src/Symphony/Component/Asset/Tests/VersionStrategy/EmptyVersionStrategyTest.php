<?php

/*
 * This file is part of the Symphony package.
 *
 * (c) Fabien Potencier <fabien@symphony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symphony\Component\Asset\Tests\VersionStrategy;

use PHPUnit\Framework\TestCase;
use Symphony\Component\Asset\VersionStrategy\EmptyVersionStrategy;

class EmptyVersionStrategyTest extends TestCase
{
    public function testGetVersion()
    {
        $emptyVersionStrategy = new EmptyVersionStrategy();
        $path = 'test-path';

        $this->assertEmpty($emptyVersionStrategy->getVersion($path));
    }

    public function testApplyVersion()
    {
        $emptyVersionStrategy = new EmptyVersionStrategy();
        $path = 'test-path';

        $this->assertEquals($path, $emptyVersionStrategy->applyVersion($path));
    }
}
